<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommandsExtendedTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webgis_cmd_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function write(string $name, array $features): void
    {
        file_put_contents("{$this->dir}/{$name}.json", json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    private function f(array $geom, array $props): array
    {
        return ['type' => 'Feature', 'geometry' => $geom, 'properties' => $props];
    }

    private const POINT = ['type' => 'Point', 'coordinates' => [105.2, 10.6]];
    private const LINE = ['type' => 'LineString', 'coordinates' => [[105.2, 10.6], [105.21, 10.6]]];

    public function test_empty_directory_imports_nothing_successfully(): void
    {
        $before = DB::connection('pgsql')->table('transects')->count();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful()->expectsOutputToContain('Import xong');
        $this->assertSame($before, DB::connection('pgsql')->table('transects')->count());
    }

    public function test_invalid_json_fails_and_rolls_back(): void
    {
        file_put_contents("{$this->dir}/shorelines.json", '{khong phai json');
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
    }

    public function test_dry_run_reports_message(): void
    {
        $this->write('hotspots', [$this->f(self::LINE, ['severity' => 2.0, 'period_start_year' => 2018, 'period_end_year' => 2026, 'notes' => 'x'])]);
        $before = DB::connection('pgsql')->table('erosion_hotspots')->count();
        $this->artisan('webgis:import', ['dir' => $this->dir, '--dry-run' => true])->assertSuccessful()->expectsOutputToContain('Dry-run xong');
        $this->assertSame($before, DB::connection('pgsql')->table('erosion_hotspots')->count());
    }

    public function test_hotspots_layer_is_replaced_not_appended(): void
    {
        $this->write('hotspots', [$this->f(self::LINE, ['severity' => 2.0, 'period_start_year' => 2018, 'period_end_year' => 2026, 'notes' => 'moi'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, DB::connection('pgsql')->table('erosion_hotspots')->count());
    }

    public static function dangerLevels(): array
    {
        return [['dac_biet_nguy_hiem', true], ['nguy_hiem', true], ['binh_thuong', true], ['rat_nguy_hiem', false], ['NGUY_HIEM', false], ['', false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dangerLevels')]
    public function test_reference_danger_level_validation(string $level, bool $ok): void
    {
        $this->write('reference', [$this->f(self::POINT, ['event_date' => '2021-05-05', 'danger_level' => $level])]);
        $cmd = $this->artisan('webgis:import', ['dir' => $this->dir]);
        $ok ? $cmd->assertSuccessful() : $cmd->assertFailed();
    }

    public function test_failed_import_leaves_existing_data_untouched(): void
    {
        $pg = DB::connection('pgsql');
        $this->write('reference', [$this->f(self::POINT, ['event_date' => '2021-05-05', 'danger_level' => 'sai'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
        $this->assertSame(2, $pg->table('reference_landslide_events')->count());
    }

    public function test_trailing_slash_in_dir_is_accepted(): void
    {
        $this->artisan('webgis:import', ['dir' => $this->dir.'/'])->assertSuccessful();
    }

    public function test_missing_required_argument(): void
    {
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->artisan('webgis:import');
    }

    public function test_transect_removed_years_are_deleted_from_series(): void
    {
        $mk = fn (array $d) => $this->write('transects', [$this->f(self::LINE, ['transect_code' => 'Z01', 'distances' => $d])]);
        $mk(['2018' => 1, '2019' => 2, '2020' => 3]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $mk(['2018' => 1]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $pg = DB::connection('pgsql');
        $id = $pg->table('transects')->where('transect_code', 'Z01')->value('id');
        $this->assertSame(1, $pg->table('transect_shoreline_distances')->where('transect_id', $id)->count());
    }

    public function test_transect_without_stats_is_imported_without_stats_row(): void
    {
        $this->write('transects', [$this->f(self::LINE, ['transect_code' => 'Z02', 'distances' => ['2018' => 5]])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $pg = DB::connection('pgsql');
        $id = $pg->table('transects')->where('transect_code', 'Z02')->value('id');
        $this->assertNotNull($id);
        $this->assertSame(0, $pg->table('shoreline_change_stats')->where('transect_id', $id)->count());
    }

    public function test_imported_data_is_served_by_api(): void
    {
        $this->write('transects', [$this->f(self::LINE, ['transect_code' => 'Z03', 'distances' => ['2018' => 5], 'stats' => ['change_class' => 'stable', 'lrr_m_per_year' => 0.1]])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->getJson('/api/transects?change_class=stable')->assertJsonCount(4, 'features');
    }

    public function test_baseline_is_kept_single_across_imports(): void
    {
        $this->write('baseline', [$this->f(self::LINE, [])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(1, DB::connection('pgsql')->table('baseline')->count());
    }

    public function test_compare_reference_default_buffer_output(): void
    {
        $this->artisan('webgis:compare-reference')->assertSuccessful()->expectsOutputToContain('sự kiện có hotspot trong bán kính 500 m');
    }

    public function test_compare_reference_custom_buffer(): void
    {
        $this->artisan('webgis:compare-reference', ['--buffer' => 1])->assertSuccessful()->expectsOutputToContain('bán kính 1 m');
    }

    public function test_compare_reference_huge_buffer_matches_all(): void
    {
        $this->artisan('webgis:compare-reference', ['--buffer' => 1000000])->assertSuccessful()->expectsOutputToContain('2/2');
    }

    public function test_compare_reference_without_events_warns(): void
    {
        DB::connection('pgsql')->table('reference_landslide_events')->delete();
        $this->artisan('webgis:compare-reference')->assertSuccessful()->expectsOutputToContain('Không có sự kiện tham chiếu');
    }

    public function test_compare_reference_without_hotspots_reports_none(): void
    {
        DB::connection('pgsql')->table('erosion_hotspots')->delete();
        $this->artisan('webgis:compare-reference')->assertSuccessful()->expectsOutputToContain('0/2');
    }

    public function test_commands_are_registered(): void
    {
        $all = array_keys(\Artisan::all());
        $this->assertContains('webgis:import', $all);
        $this->assertContains('webgis:compare-reference', $all);
    }
}
