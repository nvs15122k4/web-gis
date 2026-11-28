<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nạp dữ liệu THẬT dựng bằng QGIS (pipeline/data/qgis_gui/giai_doan2/export, kèm sự kiện sạt lở tham chiếu từ pipeline/output)
 * rồi kiểm số liệu chốt: 270 transect, phân lớp 52/209/8/1, 1 hotspot (T0089), 2 sự kiện sạt lở tham chiếu;
 * /api/summary phải khớp. Chạy trong transaction nên được rollback.
 */
class ImportRealPipelineOutputTest extends TestCase
{
    private string $tmp = '';

    protected function tearDown(): void
    {
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            array_map('unlink', glob("{$this->tmp}/*"));
            rmdir($this->tmp);
        }
        parent::tearDown();
    }

    /** Thư mục tạm gồm GeoJSON xuất từ QGIS + reference.json (sạt lở tham chiếu, không do QGIS tạo). */
    private function outputDir(): string
    {
        $qgis = base_path('../pipeline/data/qgis_gui/giai_doan2/export');
        $ref = base_path('../pipeline/output/reference.json');
        foreach (['baseline', 'shorelines', 'transects', 'hotspots'] as $f) {
            if (! is_file("{$qgis}/{$f}.geojson")) {
                $this->markTestSkipped("Thiếu {$qgis}/{$f}.geojson — dựng dữ liệu trong QGIS trước khi kiểm thử này.");
            }
        }
        if (! is_file($ref)) {
            $this->markTestSkipped('Thiếu pipeline/output/reference.json.');
        }
        $this->tmp = sys_get_temp_dir().'/webgis_real_'.uniqid();
        mkdir($this->tmp);
        foreach (['baseline', 'shorelines', 'transects', 'hotspots'] as $f) {
            copy("{$qgis}/{$f}.geojson", "{$this->tmp}/{$f}.geojson");
        }
        copy($ref, "{$this->tmp}/reference.json");

        return $this->tmp;
    }

    private function pg()
    {
        return DB::connection('pgsql');
    }

    private function importReal(): void
    {
        $dir = $this->outputDir();
        // Bỏ dữ liệu fixture để chỉ còn dữ liệu thật (rollback sau test).
        foreach (['shoreline_change_stats', 'transect_shoreline_distances', 'transects', 'shorelines'] as $t) {
            $this->pg()->table($t)->delete();
        }
        $this->artisan('webgis:import', ['dir' => $dir])->assertSuccessful();
    }

    public function test_real_output_is_imported_with_expected_counts(): void
    {
        $this->importReal();

        $this->assertSame(270, $this->pg()->table('transects')->count());
        $this->assertSame(270, $this->pg()->table('shoreline_change_stats')->count());
        $this->assertSame(1, $this->pg()->table('erosion_hotspots')->count());
        $this->assertSame(2, $this->pg()->table('reference_landslide_events')->count());
    }

    public function test_change_class_distribution_matches_qgis_summary(): void
    {
        $this->importReal();

        $byClass = $this->pg()->table('shoreline_change_stats')
            ->selectRaw('change_class, COUNT(*) AS n')->groupBy('change_class')->pluck('n', 'change_class')
            ->map(fn ($n) => (int) $n)->all();
        ksort($byClass);

        $this->assertSame(['accretion' => 52, 'mild_erosion' => 8, 'severe_erosion' => 1, 'stable' => 209], $byClass);
    }

    public function test_hotspots_are_on_severe_erosion_transects(): void
    {
        $this->importReal();

        $severe = $this->pg()->table('shoreline_change_stats as s')->join('transects as t', 't.id', '=', 's.transect_id')
            ->where('s.change_class', 'severe_erosion')->pluck('t.transect_code')->all();
        sort($severe);
        $this->assertSame(['T0089'], $severe);

        $notes = $this->pg()->table('erosion_hotspots')->pluck('notes')->implode(' ');
        foreach (['T0089'] as $code) {
            $this->assertStringContainsString($code, $notes, "Ghi chú hotspot phải nêu transect $code");
        }
    }

    public function test_summary_api_matches_imported_numbers(): void
    {
        $this->importReal();

        $s = $this->getJson('/api/summary')->assertOk()->json();

        $this->assertSame(270, $s['transects']['total']);
        $this->assertSame(
            ['accretion' => 52, 'stable' => 209, 'mild_erosion' => 8, 'severe_erosion' => 1],
            $s['transects']['by_class']
        );
        $this->assertSame(1, $s['hotspots']['count']);
        $this->assertSame(2, $s['reference_events']['count']);
        $this->assertSame('T0006', $s['transects']['max_erosion']['transect_code']);
    }
}
