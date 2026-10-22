<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dữ liệu tổng hợp CỐ ĐỊNH cho Playwright/PHPUnit (Mục 9) và để chạy thử giao diện khi chưa có
 * output pipeline thật. Chạy trên CSDL riêng (`*_e2e`, `*_test`), KHÔNG dùng chung DB phát triển.
 *
 * Các tình huống biên được dựng sẵn:
 *  - năm 2022 không có composite hợp lệ (không có đường bờ, không có khoảng cách)
 *  - transect T05 thiếu thêm 2020, 2021 (gap dữ liệu giữa chuỗi)
 *  - sạt lở tham chiếu chỉ nằm ở phía bắc (gần T01–T02); phía nam (T08–T10) không có sự kiện nào
 *  - sự kiện sạt lở thứ 2 chưa có mức độ/chiều dài/địa điểm (giao diện phải ghi "Đang cập nhật")
 *
 * Quy ước dấu: distance_m = khoảng cách baseline → bờ; tốc độ dương = bồi, âm = lùi bờ.
 */
class E2eFixtureSeeder extends Seeder
{
    public const YEARS = [2018, 2019, 2020, 2021, 2023, 2024, 2025, 2026]; // thiếu 2022

    private const LON0 = 105.20;
    private const LAT0 = 10.560;
    private const LAT_STEP = 0.004;
    private const M_PER_DEG_LON = 109500.0;

    /** code => [class, LRR m/năm, năm bị thiếu thêm] */
    private const TRANSECTS = [
        'T01' => ['accretion', 2.5, []],
        'T02' => ['accretion', 1.8, []],
        'T03' => ['stable', 0.1, []],
        'T04' => ['stable', -0.2, []],
        'T05' => ['stable', 0.0, [2020, 2021]],
        'T06' => ['mild_erosion', -1.2, []],
        'T07' => ['mild_erosion', -1.8, []],
        'T08' => ['severe_erosion', -3.5, []],
        'T09' => ['severe_erosion', -4.8, []],
        'T10' => ['severe_erosion', -6.2, []],
    ];

    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        // Người dùng không phải admin, dùng cho kịch bản kiểm thử từ chối quyền.
        \App\Models\User::updateOrCreate(
            ['email' => 'viewer@webgis.test'],
            ['name' => 'Viewer', 'password' => 'password', 'role' => 'viewer']
        );

        $pg = DB::connection('pgsql');
        foreach ([
            'reference_landslide_events', 'erosion_hotspots', 'shoreline_change_stats',
            'transect_shoreline_distances', 'transects', 'shorelines', 'baseline',
        ] as $table) {
            $pg->table($table)->delete();
        }

        $lat = fn (int $i) => self::LAT0 + $i * self::LAT_STEP;
        $lonAt = fn (float $d) => self::LON0 + $d / self::M_PER_DEG_LON;
        $n = count(self::TRANSECTS);

        $baselineId = $pg->selectOne(
            'INSERT INTO baseline (geom) VALUES (ST_GeomFromText(?, 4326)) RETURNING id',
            [sprintf('LINESTRING(%F %F, %F %F)', self::LON0, $lat(0) - 0.002, self::LON0, $lat($n - 1) + 0.002)]
        )->id;

        $distances = []; // code => [year => d]
        $i = 0;
        foreach (self::TRANSECTS as $code => [$class, $lrr, $missing]) {
            $series = [];
            foreach (self::YEARS as $year) {
                if (in_array($year, $missing, true)) {
                    continue;
                }
                $noise = 0.15 * sin(($year - 2018) * 1.7 + $i);
                $series[$year] = round(800 + $lrr * ($year - 2018) + $noise, 2);
            }
            $distances[$code] = $series;

            $tid = $pg->selectOne(
                'INSERT INTO transects (transect_code, baseline_id, geom) VALUES (?, ?, ST_GeomFromText(?, 4326)) RETURNING id',
                [$code, $baselineId, sprintf('LINESTRING(%F %F, %F %F)', self::LON0, $lat($i), $lonAt(1600), $lat($i))]
            )->id;

            foreach ($series as $year => $d) {
                $pg->table('transect_shoreline_distances')->insert(['transect_id' => $tid, 'year' => $year, 'distance_m' => $d]);
            }

            $years = array_keys($series);
            $vals = array_values($series);
            [$slope, $r2, $se] = $this->regress($years, $vals);
            $span = end($years) - $years[0];
            $pg->table('shoreline_change_stats')->insert([
                'transect_id' => $tid,
                'nsm_m' => round(end($vals) - $vals[0], 2),
                'epr_m_per_year' => round((end($vals) - $vals[0]) / $span, 3),
                'lrr_m_per_year' => round($slope, 3),
                'se_lrr' => round($se, 3),
                'r_squared' => round($r2, 3),
                'is_significant' => $class !== 'stable',
                'change_class' => $class,
                'consecutive_erosion_periods' => match ($class) { 'severe_erosion' => 4, 'mild_erosion' => 2, default => 0 },
                'u_shoreline_avg' => 10.0,
            ]);
            $i++;
        }

        // Đường bờ mỗi năm: nối điểm bờ của các transect (năm thiếu tại 1 transect thì nội suy chỉ để vẽ fixture).
        foreach (self::YEARS as $year) {
            $pts = [];
            $j = 0;
            foreach (self::TRANSECTS as $code => [, $lrr]) {
                $d = $distances[$code][$year] ?? (800 + $lrr * ($year - 2018));
                $pts[] = sprintf('%F %F', $lonAt($d), $lat($j));
                $j++;
            }
            $pg->statement(
                'INSERT INTO shorelines (year, water_index, source_image_date, geom)
                 VALUES (?, ?, ?, ST_Multi(ST_GeomFromText(?, 4326)))',
                [$year, 'MNDWI', "{$year}-03-15", 'LINESTRING('.implode(',', $pts).')']
            );
        }

        // Hotspot: lùi mạnh (T08–T10) và lùi nhẹ (T06–T07) — severity = tốc độ lùi m/năm.
        foreach ([[7, 9, 4.8, 'Đoạn lùi bờ mạnh T08–T10'], [5, 6, 1.5, 'Đoạn lùi bờ nhẹ T06–T07']] as [$a, $b, $sev, $note]) {
            $pg->statement(
                'INSERT INTO erosion_hotspots (geom, severity, period_start_year, period_end_year, notes)
                 VALUES (ST_GeomFromText(?, 4326), ?, 2018, 2026, ?)',
                [sprintf('LINESTRING(%F %F, %F %F)', $lonAt(800 - $sev * 8), $lat($a), $lonAt(800 - $sev * 8), $lat($b)), $sev, $note]
            );
        }

        // Sạt lở tham chiếu 2019–2020: chỉ ở phía bắc.
        foreach ([
            [0, '2019-08-14', 'Sạt lở bờ sông Hậu tại Bình Mỹ (fixture)', 'nguy_hiem', 120.0, 'Ấp Bình Hòa (fixture)'],
            [1, '2020-03-02', 'Sạt lở đoạn kế cận (fixture)', null, null, null],
        ] as [$k, $date, $desc, $level, $length, $location]) {
            $pg->statement(
                'INSERT INTO reference_landslide_events (geom, event_date, description, source, danger_level, length_m, location)
                 VALUES (ST_GeomFromText(?, 4326), ?, ?, ?, ?, ?, ?)',
                [sprintf('POINT(%F %F)', $lonAt(790), $lat($k) + 0.001), $date, $desc, 'Fixture kiểm thử', $level, $length, $location]
            );
        }
    }

    /** @return array{0: float, 1: float, 2: float} [độ dốc, R², sai số chuẩn của độ dốc] */
    private function regress(array $x, array $y): array
    {
        $n = count($x);
        $mx = array_sum($x) / $n;
        $my = array_sum($y) / $n;
        $sxx = $sxy = $syy = 0.0;
        foreach ($x as $k => $xv) {
            $sxx += ($xv - $mx) ** 2;
            $sxy += ($xv - $mx) * ($y[$k] - $my);
            $syy += ($y[$k] - $my) ** 2;
        }
        $slope = $sxy / $sxx;
        $r2 = $syy > 0 ? ($sxy ** 2) / ($sxx * $syy) : 0.0;
        $sse = max($syy - $slope * $sxy, 0.0);
        $se = $n > 2 ? sqrt($sse / ($n - 2) / $sxx) : 0.0;

        return [$slope, $r2, $se];
    }
}
