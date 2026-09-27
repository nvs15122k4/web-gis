<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Đối chiếu hotspot với sạt lở tham chiếu 2019–2020 (Mục 2.4 đề cương) — chỉ để kiểm tra, không dùng để huấn luyện. */
class CompareReferenceEvents extends Command
{
    protected $signature = 'webgis:compare-reference {--buffer=500 : Bán kính coi là "trùng khớp" (m)}';

    protected $description = 'Đối chiếu erosion_hotspots với reference_landslide_events';

    public function handle(): int
    {
        $buffer = (float) $this->option('buffer');
        $rows = DB::connection('pgsql')->select('
            SELECT e.id, e.event_date, e.description,
                   MIN(ST_Distance(e.geom::geography, h.geom::geography)) AS nearest_m
            FROM reference_landslide_events e
            LEFT JOIN erosion_hotspots h ON TRUE
            GROUP BY e.id, e.event_date, e.description
            ORDER BY e.event_date');

        if (! $rows) {
            $this->warn('Không có sự kiện tham chiếu.');

            return self::SUCCESS;
        }

        $hit = 0;
        foreach ($rows as $r) {
            $ok = $r->nearest_m !== null && $r->nearest_m <= $buffer;
            $hit += $ok;
            $this->line(sprintf('  #%d %s  hotspot gần nhất: %s  %s', $r->id, $r->event_date ?? '—',
                $r->nearest_m === null ? 'không có' : round($r->nearest_m).' m', $ok ? '✓ trùng khớp' : '✗'));
        }
        $this->info(sprintf('%d/%d sự kiện có hotspot trong bán kính %d m.', $hit, count($rows), $buffer));

        return self::SUCCESS;
    }
}
