<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportIdempotencyTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webgis_idem_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function pg()
    {
        return DB::connection('pgsql');
    }

    private function write(string $name, array $features): void
    {
        file_put_contents("{$this->dir}/{$name}.json", json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    private function f(array $geom, array $props = []): array
    {
        return ['type' => 'Feature', 'geometry' => $geom, 'properties' => $props];
    }

    private function line(float $lon = 105.3): array
    {
        return ['type' => 'LineString', 'coordinates' => [[$lon, 10.6], [$lon + 0.01, 10.6]]];
    }

    private function full(array $distances = ['2018' => 100, '2020' => 90], float $lrr = -2.0): void
    {
        $this->write('baseline', [$this->f(['type' => 'LineString', 'coordinates' => [[105.3, 10.5], [105.3, 10.7]]])]);
        $this->write('shorelines', [$this->f(['type' => 'LineString', 'coordinates' => [[105.31, 10.5], [105.31, 10.7]]], ['year' => 2031, 'water_index' => 'MNDWI', 'source_image_date' => '2031-01-01'])]);
        $this->write('transects', [
            $this->f($this->line(), ['transect_code' => 'N01', 'distances' => $distances, 'stats' => ['nsm_m' => -10, 'epr_m_per_year' => -1, 'lrr_m_per_year' => $lrr, 'change_class' => 'mild_erosion', 'is_significant' => true, 'consecutive_erosion_periods' => 2]]),
            $this->f($this->line(105.4), ['transect_code' => 'N02', 'distances' => ['2018' => 50], 'stats' => ['change_class' => 'stable']]),
        ]);
        $this->write('hotspots', [$this->f($this->line(), ['severity' => 3.3, 'period_start_year' => 2018, 'period_end_year' => 2026, 'notes' => 'H'])]);
        $this->write('reference', [$this->f(['type' => 'Point', 'coordinates' => [105.3, 10.6]], ['event_date' => '2019-01-01', 'description' => 'R', 'source' => 'S'])]);
    }

    private function snapshot(): array
    {
        return [
            'transects' => $this->pg()->table('transects')->orderBy('transect_code')->get(['id', 'transect_code', 'baseline_id'])->map(fn ($r) => (array) $r)->all(),
            'dist' => $this->pg()->table('transect_shoreline_distances')->orderBy('transect_id')->orderBy('year')->get(['transect_id', 'year', 'distance_m'])->map(fn ($r) => (array) $r)->all(),
            'stats' => $this->pg()->table('shoreline_change_stats')->orderBy('transect_id')->get()->map(fn ($r) => (array) $r)->all(),
            'shore' => $this->pg()->table('shorelines')->orderBy('year')->get(['id', 'year', 'water_index'])->map(fn ($r) => (array) $r)->all(),
            'baseline' => $this->pg()->table('baseline')->pluck('id')->all(),
        ];
    }

    private function counts(): array
    {
        return array_map(fn ($t) => $this->pg()->table($t)->count(), ['baseline', 'shorelines', 'transects', 'transect_shoreline_distances', 'shoreline_change_stats', 'erosion_hotspots', 'reference_landslide_events']);
    }

    public function test_three_identical_imports_give_identical_database_state(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $first = $this->snapshot();
        $counts = $this->counts();
        foreach ([1, 2] as $_) {
            $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        }
        $this->assertSame($first, $this->snapshot());
        $this->assertSame($counts, $this->counts());
    }

    public function test_output_report_lists_every_layer_and_is_stable_across_runs(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])
            ->expectsOutputToContain('shorelines')->expectsOutputToContain('transects')
            ->expectsOutputToContain('hotspots')->expectsOutputToContain('reference')
            ->expectsOutputToContain('Import xong.')->assertSuccessful();
        $this->artisan('webgis:import', ['dir' => $this->dir])->expectsOutputToContain('Import xong.')->assertSuccessful();
    }

    public function test_transect_ids_are_stable_but_hotspot_ids_change_on_reimport(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $tid = $this->pg()->table('transects')->where('transect_code', 'N01')->value('id');
        $hid = $this->pg()->table('erosion_hotspots')->value('id');
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($tid, $this->pg()->table('transects')->where('transect_code', 'N01')->value('id'));
        // Ghi nhận: lớp hotspot/reference được xoá-tạo-lại nên ID đổi (URL/bookmark theo ID hotspot sẽ hỏng).
        $this->assertNotSame($hid, $this->pg()->table('erosion_hotspots')->value('id'));
    }

    public function test_api_output_identical_after_reimport_except_replaced_layer_ids(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $a = $this->getJson('/api/transects')->json();
        $s = $this->getJson('/api/summary')->json();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($a, $this->getJson('/api/transects')->json());
        $this->assertSame($s, $this->getJson('/api/summary')->json());
    }

    public function test_reimport_with_fewer_distance_years_shrinks_series(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->full(['2018' => 100]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $id = $this->pg()->table('transects')->where('transect_code', 'N01')->value('id');
        $this->assertSame(1, $this->pg()->table('transect_shoreline_distances')->where('transect_id', $id)->count());
    }

    public function test_reimport_with_empty_distances_deletes_series(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->full([]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $id = $this->pg()->table('transects')->where('transect_code', 'N01')->value('id');
        $this->assertSame(0, $this->pg()->table('transect_shoreline_distances')->where('transect_id', $id)->count());
    }

    public function test_reimport_updates_distance_values_without_duplicates(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->full(['2018' => 111, '2020' => 99]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $id = $this->pg()->table('transects')->where('transect_code', 'N01')->value('id');
        $this->assertEquals([111, 99], $this->pg()->table('transect_shoreline_distances')->where('transect_id', $id)->orderBy('year')->pluck('distance_m')->all());
    }

    public function test_stale_stats_remain_when_reimport_omits_stats(): void
    {
        // Ghi nhận: nếu lần nạp sau bỏ khối "stats" thì dòng stats cũ vẫn giữ nguyên (không xoá).
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->write('transects', [$this->f($this->line(), ['transect_code' => 'N01', 'distances' => ['2018' => 100]])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $id = $this->pg()->table('transects')->where('transect_code', 'N01')->value('id');
        $this->assertSame('mild_erosion', $this->pg()->table('shoreline_change_stats')->where('transect_id', $id)->value('change_class'));
    }

    public function test_transect_missing_from_new_output_is_not_deleted(): void
    {
        // Ghi nhận: UPSERT không xoá transect đã biến mất khỏi output mới.
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->write('transects', [$this->f($this->line(), ['transect_code' => 'N01', 'distances' => ['2018' => 100]])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, $this->pg()->table('transects')->where('transect_code', 'N02')->count());
    }

    public function test_missing_hotspot_file_keeps_existing_hotspots(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        unlink("{$this->dir}/hotspots.json");
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, $this->pg()->table('erosion_hotspots')->count());
    }

    public function test_empty_hotspot_collection_keeps_existing_hotspots(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->write('hotspots', []);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        // Ghi nhận: collection rỗng KHÔNG xoá hotspot cũ (không thể "xoá hết" qua import).
        $this->assertSame(1, $this->pg()->table('erosion_hotspots')->count());
    }

    public function test_hotspot_count_does_not_double_on_repeat(): void
    {
        $this->full();
        foreach ([1, 2, 3] as $_) {
            $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        }
        $this->assertSame(1, $this->pg()->table('erosion_hotspots')->count());
        $this->assertSame(1, $this->pg()->table('reference_landslide_events')->count());
    }

    public function test_dry_run_after_real_import_changes_nothing(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $snap = $this->snapshot();
        $this->full(['2018' => 1], -9.9);
        $this->artisan('webgis:import', ['dir' => $this->dir, '--dry-run' => true])->assertSuccessful();
        $this->assertSame($snap, $this->snapshot());
    }

    public function test_dry_run_does_not_consume_sequence_visible_ids_of_real_run(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, $this->pg()->table('transects')->where('transect_code', 'N01')->count());
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, $this->pg()->table('transects')->where('transect_code', 'N01')->count());
    }

    public function test_partial_failure_in_later_layer_rolls_back_earlier_layers(): void
    {
        $this->full();
        $this->write('reference', [$this->f(['type' => 'Point', 'coordinates' => [105.3, 10.6]], ['event_date' => '2019-01-01', 'danger_level' => 'sai'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
        $this->assertSame(0, $this->pg()->table('transects')->where('transect_code', 'N01')->count());
        $this->assertSame(0, $this->pg()->table('shorelines')->where('year', 2031)->count());
        $this->assertSame(2, $this->pg()->table('erosion_hotspots')->count()); // hotspot fixture còn nguyên
    }

    public function test_failed_reimport_leaves_previous_good_import_intact(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $snap = $this->snapshot();
        file_put_contents("{$this->dir}/hotspots.json", '{not json');
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
        $this->assertSame($snap, $this->snapshot());
    }

    public function test_invalid_geometry_text_rolls_back(): void
    {
        $this->full();
        $this->write('shorelines', [$this->f(['type' => 'Nope', 'coordinates' => 1], ['year' => 2032, 'water_index' => 'MNDWI'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
        $this->assertSame(0, $this->pg()->table('transects')->where('transect_code', 'N01')->count());
    }

    public function test_wrong_geometry_type_for_layer_fails_cleanly(): void
    {
        $this->full();
        $this->write('transects', [$this->f(['type' => 'Point', 'coordinates' => [105.3, 10.6]], ['transect_code' => 'PT'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
        $this->assertSame(0, $this->pg()->table('transects')->where('transect_code', 'PT')->count());
    }

    public function test_features_with_missing_properties_key_fail_not_crash(): void
    {
        $this->full();
        $this->write('shorelines', [['type' => 'Feature', 'geometry' => $this->line()]]);
        $r = $this->artisan('webgis:import', ['dir' => $this->dir]);
        $r->assertExitCode(1);
    }

    public function test_import_into_empty_dir_is_noop_even_with_existing_data(): void
    {
        $before = $this->counts();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($before, $this->counts());
    }

    public function test_same_transect_code_twice_in_one_file_is_merged(): void
    {
        $this->full();
        $this->write('transects', [
            $this->f($this->line(), ['transect_code' => 'DUP', 'distances' => ['2018' => 1]]),
            $this->f($this->line(105.5), ['transect_code' => 'DUP', 'distances' => ['2018' => 2]]),
        ]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, $this->pg()->table('transects')->where('transect_code', 'DUP')->count());
        $id = $this->pg()->table('transects')->where('transect_code', 'DUP')->value('id');
        $this->assertEquals(2, $this->pg()->table('transect_shoreline_distances')->where('transect_id', $id)->value('distance_m'));
    }

    public function test_import_keeps_unicode_text_round_trip(): void
    {
        $this->full();
        $this->write('hotspots', [$this->f($this->line(), ['severity' => 2, 'notes' => 'Đoạn sạt lở ấp Bình Hòa – “nặng”'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertStringContainsString('Đoạn sạt lở ấp Bình Hòa – “nặng”', $this->get('/api/export/hotspots.csv')->getContent());
    }

    public function test_sql_injection_in_imported_text_is_stored_literally(): void
    {
        $this->full();
        $evil = "x'); DROP TABLE transects; --";
        $this->write('hotspots', [$this->f($this->line(), ['severity' => 2, 'notes' => $evil])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($evil, $this->pg()->table('erosion_hotspots')->value('notes'));
        $this->assertGreaterThan(0, $this->pg()->table('transects')->count());
    }

    public function test_import_does_not_touch_mysql_tables(): void
    {
        $before = [DB::table('threshold_configs')->count(), DB::table('users')->count(), DB::table('activity_logs')->count()];
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($before, [DB::table('threshold_configs')->count(), DB::table('users')->count(), DB::table('activity_logs')->count()]);
    }

    public function test_compare_reference_is_read_only_and_repeatable(): void
    {
        $before = $this->counts();
        $this->artisan('webgis:compare-reference')->assertSuccessful();
        $this->artisan('webgis:compare-reference')->assertSuccessful();
        $this->assertSame($before, $this->counts());
    }

    public function test_compare_reference_after_import_uses_imported_hotspots(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->artisan('webgis:compare-reference', ['--buffer' => 100])->expectsOutputToContain('1/1')->assertSuccessful();
    }
}
