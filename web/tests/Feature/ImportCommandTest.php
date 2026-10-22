<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImportCommandTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webgis_import_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob("{$this->dir}/*"));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function feature(array $geometry, array $props): array
    {
        return ['type' => 'Feature', 'geometry' => $geometry, 'properties' => $props];
    }

    private function write(string $name, array $features): void
    {
        file_put_contents("{$this->dir}/{$name}.geojson", json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    private function fixtureFiles(float $lrr): void
    {
        $line = ['type' => 'LineString', 'coordinates' => [[105.2, 10.6], [105.21, 10.6]]];
        $this->write('baseline', [$this->feature(['type' => 'LineString', 'coordinates' => [[105.2, 10.59], [105.2, 10.61]]], [])]);
        $this->write('shorelines', [$this->feature(['type' => 'LineString', 'coordinates' => [[105.21, 10.59], [105.21, 10.61]]], ['year' => 2030, 'water_index' => 'MNDWI'])]);
        $this->write('transects', [$this->feature($line, [
            'transect_code' => 'X01',
            'distances' => ['2018' => 100, '2019' => null, '2020' => 90],
            'stats' => ['nsm_m' => -10, 'lrr_m_per_year' => $lrr, 'epr_m_per_year' => -5, 'change_class' => 'severe_erosion', 'is_significant' => true],
        ])]);
    }

    public function test_import_upserts_without_changing_ids(): void
    {
        $this->fixtureFiles(-5.0);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();

        $pg = DB::connection('pgsql');
        $id = $pg->table('transects')->where('transect_code', 'X01')->value('id');
        $this->assertNotNull($id);
        // năm null (thiếu dữ liệu) không được ghi, không nội suy
        $this->assertSame([2018, 2020], $pg->table('transect_shoreline_distances')->where('transect_id', $id)->orderBy('year')->pluck('year')->map(fn ($y) => (int) $y)->all());
        $this->assertEquals(-5.0, $pg->table('shoreline_change_stats')->where('transect_id', $id)->value('lrr_m_per_year'));

        // chạy lại với số liệu mới: cùng ID, giá trị cập nhật, không nhân đôi
        $this->fixtureFiles(-6.5);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($id, $pg->table('transects')->where('transect_code', 'X01')->value('id'));
        $this->assertSame(1, $pg->table('transects')->where('transect_code', 'X01')->count());
        $this->assertEquals(-6.5, $pg->table('shoreline_change_stats')->where('transect_id', $id)->value('lrr_m_per_year'));
        $this->assertSame(1, $pg->table('shorelines')->where('year', 2030)->count());
    }

    public function test_import_reference_with_danger_level(): void
    {
        $point = ['type' => 'Point', 'coordinates' => [105.2, 10.6]];
        $this->write('reference', [
            $this->feature($point, ['event_date' => '2019-09-01', 'description' => 'A', 'source' => 'UBND', 'danger_level' => 'dac_biet_nguy_hiem', 'length_m' => 85, 'location' => 'Ấp X']),
            $this->feature($point, ['event_date' => '2020-01-01', 'description' => 'B', 'source' => 'UBND']),
        ]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();

        $rows = DB::connection('pgsql')->table('reference_landslide_events')->orderBy('event_date')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('dac_biet_nguy_hiem', $rows[0]->danger_level);
        $this->assertEquals(85, $rows[0]->length_m);
        $this->assertNull($rows[1]->danger_level); // thiếu trường: để trống, giao diện ghi "Đang cập nhật"
    }

    public function test_import_rejects_unknown_danger_level(): void
    {
        $point = ['type' => 'Point', 'coordinates' => [105.2, 10.6]];
        $this->write('reference', [$this->feature($point, ['event_date' => '2019-09-01', 'danger_level' => 'rat_nguy_hiem'])]);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertFailed();
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fixtureFiles(-5.0);
        $this->artisan('webgis:import', ['dir' => $this->dir, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, DB::connection('pgsql')->table('transects')->where('transect_code', 'X01')->count());
    }

    public function test_missing_directory_fails(): void
    {
        $this->artisan('webgis:import', ['dir' => '/khong/ton/tai'])->assertFailed();
    }

    public function test_compare_reference_reports(): void
    {
        $this->artisan('webgis:compare-reference')->assertSuccessful();
    }
}
