<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nạp output pipeline Python vào PostGIS bằng UPSERT (Mục 10: không xoá-tạo-lại ID đang tham chiếu).
 *
 * Thư mục nguồn chứa các file GeoJSON (EPSG:4326):
 *   baseline.json   1 feature LineString
 *   shorelines.json properties: year, water_index, source_image_date?
 *   transects.json  properties: transect_code, distances{"2018": m,...}, stats{nsm_m, epr_m_per_year,
 *                      lrr_m_per_year, se_lrr, r_squared, is_significant, change_class,
 *                      consecutive_erosion_periods, u_shoreline_avg}
 *   hotspots.json   properties: severity, period_start_year, period_end_year, notes
 *   reference.json  properties: event_date, description, source, danger_level?, length_m?, location?
 *                      (danger_level: dac_biet_nguy_hiem | nguy_hiem | binh_thuong — theo QĐ 01/2011/QĐ-TTg)
 * File nào không có thì bỏ qua.
 */
class ImportPipelineOutput extends Command
{
    protected $signature = 'webgis:import {dir : Thư mục chứa output GeoJSON của pipeline} {--dry-run : Chỉ kiểm tra, không ghi}';

    protected $description = 'Nạp output pipeline (GeoJSON) vào PostGIS bằng UPSERT';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        if (! is_dir($dir)) {
            $this->error("Không tìm thấy thư mục: {$dir}");

            return self::FAILURE;
        }

        $pg = DB::connection('pgsql');
        $pg->beginTransaction();

        try {
            $baselineId = $this->baseline($pg, $dir);
            $counts = [
                'shorelines' => $this->shorelines($pg, $dir),
                'transects' => $this->transects($pg, $dir, $baselineId),
                'hotspots' => $this->replaceLayer($pg, $dir, 'hotspots', 'erosion_hotspots', ['severity', 'period_start_year', 'period_end_year', 'notes']),
                'reference' => $this->replaceLayer($pg, $dir, 'reference', 'reference_landslide_events', ['event_date', 'description', 'source', 'danger_level', 'length_m', 'location']),
            ];
            $this->option('dry-run') ? $pg->rollBack() : $pg->commit();
        } catch (\Throwable $e) {
            $pg->rollBack();
            $this->error('Import thất bại, đã rollback: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach ($counts as $k => $v) {
            $this->line(sprintf('  %-11s %d', $k, $v));
        }
        $this->info($this->option('dry-run') ? 'Dry-run xong (đã rollback).' : 'Import xong.');

        return self::SUCCESS;
    }

    private function features(string $dir, string $name): array
    {
        $file = "{$dir}/{$name}.json";
        if (! is_file($file)) {
            return [];
        }
        $json = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        return $json['features'] ?? [];
    }

    private function baseline($pg, string $dir): int
    {
        foreach ($this->features($dir, 'baseline') as $f) {
            // Chỉ giữ 1 baseline: cập nhật hình học tại chỗ để không đổi ID mà transect đang tham chiếu.
            $existing = $pg->table('baseline')->orderBy('id')->value('id');
            $geom = json_encode($f['geometry']);
            if ($existing) {
                $pg->statement('UPDATE baseline SET geom = ST_SetSRID(ST_GeomFromGeoJSON(?), 4326) WHERE id = ?', [$geom, $existing]);

                return $existing;
            }

            return $pg->selectOne('INSERT INTO baseline (geom) VALUES (ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)) RETURNING id', [$geom])->id;
        }

        return (int) $pg->table('baseline')->orderBy('id')->value('id');
    }

    private function shorelines($pg, string $dir): int
    {
        $n = 0;
        foreach ($this->features($dir, 'shorelines') as $f) {
            $p = $f['properties'];
            $pg->statement(
                'INSERT INTO shorelines (year, water_index, source_image_date, geom)
                 VALUES (?, ?, ?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))
                 ON CONFLICT (year, water_index) DO UPDATE
                 SET source_image_date = EXCLUDED.source_image_date, geom = EXCLUDED.geom',
                [$p['year'], $p['water_index'], $p['source_image_date'] ?? null, json_encode($f['geometry'])]
            );
            $n++;
        }

        return $n;
    }

    private function transects($pg, string $dir, int $baselineId): int
    {
        $n = 0;
        foreach ($this->features($dir, 'transects') as $f) {
            $p = $f['properties'];
            $tid = $pg->selectOne(
                'INSERT INTO transects (transect_code, baseline_id, geom)
                 VALUES (?, ?, ST_SetSRID(ST_GeomFromGeoJSON(?), 4326))
                 ON CONFLICT (transect_code) DO UPDATE SET geom = EXCLUDED.geom, baseline_id = EXCLUDED.baseline_id
                 RETURNING id',
                [$p['transect_code'], $baselineId, json_encode($f['geometry'])]
            )->id;

            $years = array_map('intval', array_keys($p['distances'] ?? []));
            foreach ($p['distances'] ?? [] as $year => $d) {
                if ($d === null) {
                    continue; // năm thiếu dữ liệu: không ghi, không nội suy
                }
                $pg->statement(
                    'INSERT INTO transect_shoreline_distances (transect_id, year, distance_m) VALUES (?, ?, ?)
                     ON CONFLICT (transect_id, year) DO UPDATE SET distance_m = EXCLUDED.distance_m',
                    [$tid, (int) $year, $d]
                );
            }
            // Năm không còn trong output mới thì xoá khỏi chuỗi.
            $q = $pg->table('transect_shoreline_distances')->where('transect_id', $tid);
            $years ? $q->whereNotIn('year', $years)->delete() : $q->delete();

            if (! empty($p['stats'])) {
                $s = $p['stats'];
                $cols = ['nsm_m', 'epr_m_per_year', 'lrr_m_per_year', 'se_lrr', 'r_squared', 'is_significant',
                    'change_class', 'consecutive_erosion_periods', 'u_shoreline_avg'];
                $vals = array_map(fn ($c) => $s[$c] ?? ($c === 'is_significant' ? false : ($c === 'consecutive_erosion_periods' ? 0 : null)), $cols);
                $set = implode(', ', array_map(fn ($c) => "{$c} = EXCLUDED.{$c}", $cols));
                $pg->statement(
                    'INSERT INTO shoreline_change_stats (transect_id, '.implode(', ', $cols).') VALUES (?'.str_repeat(', ?', count($cols)).")
                     ON CONFLICT (transect_id) DO UPDATE SET {$set}",
                    [$tid, ...$vals]
                );
            }
            $n++;
        }

        return $n;
    }

    /** Lớp không có khoá tự nhiên (hotspot, sạt lở tham chiếu): không lớp nào tham chiếu tới ID nên thay toàn bộ. */
    private function replaceLayer($pg, string $dir, string $file, string $table, array $cols): int
    {
        $features = $this->features($dir, $file);
        if (! $features) {
            return 0;
        }
        $pg->table($table)->delete();
        foreach ($features as $f) {
            $vals = array_map(fn ($c) => $f['properties'][$c] ?? null, $cols);
            $pg->statement(
                "INSERT INTO {$table} (geom, ".implode(', ', $cols).') VALUES (ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)'.str_repeat(', ?', count($cols)).')',
                [json_encode($f['geometry']), ...$vals]
            );
        }

        return count($features);
    }
}
