<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nạp output pipeline THẬT (pipeline/output) rồi kiểm số liệu chốt: 267 transect, phân lớp 81/167/18/1,
 * 1 hotspot (T0094), 2 sự kiện sạt lở tham chiếu; /api/summary phải khớp. Chạy trong transaction nên được rollback.
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

        $this->assertSame(267, $this->pg()->table('transects')->count());
        $this->assertSame(267, $this->pg()->table('shoreline_change_stats')->count());
        $this->assertSame(1, $this->pg()->table('erosion_hotspots')->count());
        $this->assertSame(2, $this->pg()->table('reference_landslide_events')->count());
    }

    public function test_change_class_distribution_matches_pipeline_summary(): void
    {
        $this->importReal();

        $byClass = $this->pg()->table('shoreline_change_stats')
            ->selectRaw('change_class, COUNT(*) AS n')->groupBy('change_class')->pluck('n', 'change_class')
            ->map(fn ($n) => (int) $n)->all();
        ksort($byClass);

        $this->assertSame(['accretion' => 81, 'mild_erosion' => 18, 'severe_erosion' => 1, 'stable' => 167], $byClass);
    }

    public function test_single_hotspot_is_on_transect_t0094(): void
    {
        $this->importReal();

        $severe = $this->pg()->table('shoreline_change_stats as s')->join('transects as t', 't.id', '=', 's.transect_id')
            ->where('s.change_class', 'severe_erosion')->pluck('t.transect_code')->all();
        $this->assertSame(['T0094'], $severe);

        $hotspot = $this->pg()->table('erosion_hotspots')->first();
        $this->assertStringContainsString('T0094', (string) $hotspot->notes, 'Ghi chú hotspot phải nêu transect T0094');
    }

    public function test_summary_api_matches_imported_numbers(): void
    {
        $this->importReal();

        $s = $this->getJson('/api/summary')->assertOk()->json();

        $this->assertSame(267, $s['transects']['total']);
        $this->assertSame(
            ['accretion' => 81, 'stable' => 167, 'mild_erosion' => 18, 'severe_erosion' => 1],
            $s['transects']['by_class']
        );
        $this->assertSame(1, $s['hotspots']['count']);
        $this->assertSame(2, $s['reference_events']['count']);
        $this->assertSame('T0094', $s['transects']['max_erosion']['transect_code']);
    }
}
