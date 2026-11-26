<?php

namespace App\Console\Commands;

use App\Models\Shoreline;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nạp output pipeline Python vào PostGIS bằng UPSERT (Mục 10: không xoá-tạo-lại ID đang tham chiếu).
 *
 * Thư mục nguồn chứa các file GeoJSON (EPSG:4326):
 *   baseline.json   1 feature LineString
 *   shorelines.json properties: year, water_index, source_image_date?
 *   transects.json  properties: transect_code, distances{"2018": m,...}, stats{nsm_m, epr_m_per_year,
 *                      lrr_m_per_year, se_lrr, unc_lrr, r_squared, is_significant, change_class,
 *                      consecutive_erosion_periods, u_shoreline_avg}
 *   hotspots.json   properties: severity, period_start_year, period_end_year, notes
 *   reference.json  properties: event_date, description, source, danger_level?, length_m?, location?
 *                      (danger_level: dac_biet_nguy_hiem | nguy_hiem | binh_thuong — theo QĐ 01/2011/QĐ-TTg)
 * File nào không có thì bỏ qua.
 *
 * Với --alt=<thư mục> còn nạp thêm hai bản dữ liệu đối chứng độc lập (không đụng bản chính), cũng bằng UPSERT:
 *   <alt>/{s2c1,landsat}/shoreline/<năm>_mndwi.json   đường bờ (thường ở EPSG:32648, đổi sang 4326 khi nạp)
 *   <dir>/alt_transects.json   properties: transect_code, {s2c1,landsat}_{lrr,class,diff}
 *   <dir>/alt_compare.json     {s2c1,landsat}: scenes_used{năm: số cảnh}, low_confidence_years[]
 */
class ImportPipelineOutput extends Command
{
    protected $signature = 'webgis:import {dir : Thư mục chứa output GeoJSON của pipeline} {--dry-run : Chỉ kiểm tra, không ghi} {--alt= : Thư mục dữ liệu đối chứng (chứa s2c1/shoreline và landsat/shoreline), nạp kèm bản đối chứng}';

    protected $description = 'Nạp output pipeline (GeoJSON) vào PostGIS bằng UPSERT';

    public function handle(): int
    {
        $dir = rtrim($this->argument('dir'), '/');
        if (! is_dir($dir)) {
            $this->error("Không tìm thấy thư mục: {$dir}");

            return self::FAILURE;
        }

        $alt = $this->option('alt');
        if ($alt !== null && ! is_dir($alt)) {
            $this->error("Không tìm thấy thư mục dữ liệu đối chứng: {$alt}");

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
            if ($alt !== null) {
                $counts['alt_shorelines'] = $this->altShorelines($pg, rtrim($alt, '/'));
                $counts['alt_lrr'] = $this->altLrr($pg, $dir);
                $counts['alt_years'] = $this->altYears($pg, $dir);
            }
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
        return $this->featuresOf("{$dir}/{$name}.json");
    }

    private function featuresOf(string $file): array
    {
        return $this->readJson($file)['features'] ?? [];
    }

    private function readJson(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        return json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
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
                'INSERT INTO shorelines (year, water_index, source, source_image_date, geom)
                 VALUES (?, ?, ?, ?, ST_Multi(ST_SetSRID(ST_GeomFromGeoJSON(?), 4326)))
                 ON CONFLICT (year, water_index, source) DO UPDATE
                 SET source_image_date = EXCLUDED.source_image_date, geom = EXCLUDED.geom',
                [$p['year'], $p['water_index'], Shoreline::MAIN, $p['source_image_date'] ?? null, json_encode($f['geometry'])]
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
                $cols = ['nsm_m', 'epr_m_per_year', 'lrr_m_per_year', 'se_lrr', 'unc_lrr', 'r_squared', 'is_significant',
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

    /** Chỉ số nước của bản đối chứng được nạp: cùng chỉ số với bản chính đang hiển thị (MNDWI). */
    private const ALT_INDEX = 'mndwi';

    /** EPSG của GeoJSON (đọc từ khối crs, mặc định 4326 theo RFC 7946). */
    private function epsg(array $json): int
    {
        return preg_match('/EPSG:+(\d+)/', $json['crs']['properties']['name'] ?? '', $m) ? (int) $m[1] : 4326;
    }

    /** Đường bờ đối chứng: mỗi file <năm>_mndwi.json một feature, đổi hệ toạ độ về 4326, ghi vào shorelines với source tương ứng. */
    private function altShorelines($pg, string $alt): int
    {
        $n = 0;
        foreach (array_keys(Shoreline::ALT_SOURCES) as $source) {
            $files = glob("{$alt}/{$source}/shoreline/*_".self::ALT_INDEX.'.json') ?: [];
            sort($files);
            foreach ($files as $file) {
                $json = $this->readJson($file);
                $features = $json['features'] ?? [];
                if (count($features) !== 1) {
                    throw new \RuntimeException(basename($file).': cần đúng 1 feature đường bờ, có '.count($features));
                }
                $p = $features[0]['properties'] ?? [];
                if (! isset($p['year']) || ($p['source'] ?? $source) !== $source) {
                    throw new \RuntimeException(basename($file).": thiếu năm hoặc source không khớp '{$source}'");
                }
                $pg->statement(
                    'INSERT INTO shorelines (year, water_index, source, source_image_date, geom)
                     VALUES (?, ?, ?, NULL, ST_Multi(ST_Transform(ST_SetSRID(ST_GeomFromGeoJSON(?), ?), 4326)))
                     ON CONFLICT (year, water_index, source) DO UPDATE SET geom = EXCLUDED.geom',
                    [(int) $p['year'], strtoupper(self::ALT_INDEX), $source, json_encode($features[0]['geometry']), $this->epsg($json)]
                );
                $n++;
            }
        }

        return $n;
    }

    /** LRR đối chứng theo transect (khớp theo transect_code); transect chưa có trong CSDL thì bỏ qua, không tạo mới. */
    private function altLrr($pg, string $dir): int
    {
        $ids = $pg->table('transects')->pluck('id', 'transect_code');
        $n = 0;
        foreach ($this->features($dir, 'alt_transects') as $f) {
            $p = $f['properties'];
            $tid = $ids[$p['transect_code'] ?? null] ?? null;
            if ($tid === null) {
                continue;
            }
            foreach (array_keys(Shoreline::ALT_SOURCES) as $source) {
                $pg->statement(
                    'INSERT INTO transect_alt_lrr (transect_id, source, lrr_m_per_year, change_class, diff_vs_main) VALUES (?, ?, ?, ?, ?)
                     ON CONFLICT (transect_id, source) DO UPDATE
                     SET lrr_m_per_year = EXCLUDED.lrr_m_per_year, change_class = EXCLUDED.change_class, diff_vs_main = EXCLUDED.diff_vs_main',
                    [$tid, $source, $p["{$source}_lrr"] ?? null, $p["{$source}_class"] ?? null, $p["{$source}_diff"] ?? null]
                );
                $n++;
            }
        }

        return $n;
    }

    /** Số cảnh ảnh dùng được và cờ độ tin cậy thấp theo năm của từng nguồn đối chứng. */
    private function altYears($pg, string $dir): int
    {
        $compare = $this->readJson("{$dir}/alt_compare.json");
        $n = 0;
        foreach (array_keys(Shoreline::ALT_SOURCES) as $source) {
            $scenes = $compare[$source]['scenes_used'] ?? [];
            $low = array_map('intval', $compare[$source]['low_confidence_years'] ?? []);
            $years = array_unique([...array_map('intval', array_keys($scenes)), ...$low]);
            sort($years);
            foreach ($years as $year) {
                $pg->statement(
                    'INSERT INTO alt_source_years (source, year, scenes_used, low_confidence) VALUES (?, ?, ?, ?)
                     ON CONFLICT (source, year) DO UPDATE SET scenes_used = EXCLUDED.scenes_used, low_confidence = EXCLUDED.low_confidence',
                    [$source, $year, $scenes[(string) $year] ?? null, in_array($year, $low, true) ? 'true' : 'false']
                );
                $n++;
            }
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
