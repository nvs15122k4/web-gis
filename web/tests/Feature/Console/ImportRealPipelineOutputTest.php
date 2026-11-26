<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nạp output pipeline THẬT (pipeline/output) rồi kiểm số liệu chốt: 266 transect, phân lớp 72/179/11/4,
 * 3 hotspot (T0020, T0094, T0200–T0201), 2 sự kiện sạt lở tham chiếu; /api/summary phải khớp. Chạy trong transaction nên được rollback.
 */
class ImportRealPipelineOutputTest extends TestCase
{
    private function outputDir(): string
    {
        return realpath(base_path('../pipeline/output')) ?: base_path('../pipeline/output');
    }

    private function pg()
    {
        return DB::connection('pgsql');
    }

    private function importReal(): void
    {
        $dir = $this->outputDir();
        foreach (['baseline', 'shorelines', 'transects', 'hotspots', 'reference'] as $f) {
            if (! is_file("{$dir}/{$f}.json")) {
                $this->markTestSkipped("Thiếu pipeline/output/{$f}.json — chạy pipeline trước khi kiểm thử này.");
            }
        }
        // Bỏ dữ liệu fixture để chỉ còn dữ liệu thật (rollback sau test).
        foreach (['shoreline_change_stats', 'transect_shoreline_distances', 'transects', 'shorelines'] as $t) {
            $this->pg()->table($t)->delete();
        }
        $this->artisan('webgis:import', ['dir' => $dir])->assertSuccessful();
    }

    public function test_real_output_is_imported_with_expected_counts(): void
    {
        $this->importReal();

        $this->assertSame(266, $this->pg()->table('transects')->count());
        $this->assertSame(266, $this->pg()->table('shoreline_change_stats')->count());
        $this->assertSame(3, $this->pg()->table('erosion_hotspots')->count());
        $this->assertSame(2, $this->pg()->table('reference_landslide_events')->count());
    }

    public function test_change_class_distribution_matches_pipeline_summary(): void
    {
        $this->importReal();

        $byClass = $this->pg()->table('shoreline_change_stats')
            ->selectRaw('change_class, COUNT(*) AS n')->groupBy('change_class')->pluck('n', 'change_class')
            ->map(fn ($n) => (int) $n)->all();
        ksort($byClass);

        $this->assertSame(['accretion' => 72, 'mild_erosion' => 11, 'severe_erosion' => 4, 'stable' => 179], $byClass);
    }

    public function test_hotspots_are_on_severe_erosion_transects(): void
    {
        $this->importReal();

        $severe = $this->pg()->table('shoreline_change_stats as s')->join('transects as t', 't.id', '=', 's.transect_id')
            ->where('s.change_class', 'severe_erosion')->pluck('t.transect_code')->all();
        sort($severe);
        $this->assertSame(['T0020', 'T0094', 'T0200', 'T0201'], $severe);

        $notes = $this->pg()->table('erosion_hotspots')->pluck('notes')->implode(' ');
        foreach (['T0020', 'T0094', 'T0200'] as $code) {
            $this->assertStringContainsString($code, $notes, "Ghi chú hotspot phải nêu transect $code");
        }
    }

    public function test_summary_api_matches_imported_numbers(): void
    {
        $this->importReal();

        $s = $this->getJson('/api/summary')->assertOk()->json();

        $this->assertSame(266, $s['transects']['total']);
        $this->assertSame(
            ['accretion' => 72, 'stable' => 179, 'mild_erosion' => 11, 'severe_erosion' => 4],
            $s['transects']['by_class']
        );
        $this->assertSame(3, $s['hotspots']['count']);
        $this->assertSame(2, $s['reference_events']['count']);
        $this->assertSame('T0200', $s['transects']['max_erosion']['transect_code']);
    }
}
