<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** webgis:import --alt: nạp đường bờ + LRR đối chứng bằng UPSERT, không đụng bản chính. */
class ImportAltSourcesTest extends TestCase
{
    private string $dir;

    private string $alt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webgis_alt_out_'.uniqid();
        $this->alt = sys_get_temp_dir().'/webgis_alt_data_'.uniqid();
        mkdir($this->dir);
        foreach (['s2c1', 'landsat'] as $s) {
            mkdir("{$this->alt}/{$s}/shoreline", 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, $this->alt] as $root) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
                $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
            }
            rmdir($root);
        }
        parent::tearDown();
    }

    private function pg()
    {
        return DB::connection('pgsql');
    }

    /** Đường bờ UTM 48N (EPSG:32648) quanh 105,2°E; 10,56°N như bản đối chứng thật. */
    private function shoreline(string $source, int $year, float $dx = 0.0, ?int $epsg = 32648, ?string $propSource = null): void
    {
        $x = 521900 + $dx;
        $json = [
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => ['year' => $year, 'water_index' => 'mndwi', 'source' => $propSource ?? $source],
                'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[$x, 1167000], [$x, 1168000]]]],
            ]],
        ];
        if ($epsg) {
            $json['crs'] = ['type' => 'name', 'properties' => ['name' => "urn:ogc:def:crs:EPSG::{$epsg}"]];
        }
        file_put_contents("{$this->alt}/{$source}/shoreline/{$year}_mndwi.json", json_encode($json));
    }

    private function writeOutput(float $lrr = -2.5): void
    {
        $feat = fn (string $code, array $p) => ['type' => 'Feature', 'geometry' => null, 'properties' => ['transect_code' => $code] + $p];
        file_put_contents("{$this->dir}/alt_transects.json", json_encode(['type' => 'FeatureCollection', 'features' => [
            $feat('T01', ['s2c1_lrr' => $lrr, 's2c1_class' => 'mild_erosion', 's2c1_diff' => 0.4, 'landsat_lrr' => 2.4, 'landsat_class' => 'accretion', 'landsat_diff' => -0.1]),
            $feat('T02', ['s2c1_lrr' => null, 's2c1_class' => null, 's2c1_diff' => null, 'landsat_lrr' => 1.7, 'landsat_class' => 'accretion', 'landsat_diff' => -0.1]),
            $feat('KHONG_CO', ['s2c1_lrr' => 9, 's2c1_class' => 'accretion', 's2c1_diff' => 0, 'landsat_lrr' => 9, 'landsat_class' => 'accretion', 'landsat_diff' => 0]),
        ]]));
        file_put_contents("{$this->dir}/alt_compare.json", json_encode([
            's2c1' => ['scenes_used' => ['2018' => 2, '2020' => 10, '2022' => 0], 'low_confidence_years' => [2018, 2022]],
            'landsat' => ['scenes_used' => ['2020' => 6], 'low_confidence_years' => []],
        ]));
    }

    private function full(): void
    {
        $this->shoreline('s2c1', 2020);
        $this->shoreline('s2c1', 2018, 30);
        $this->shoreline('landsat', 2020, 60);
        $this->shoreline('landsat', 2022, 90);
        $this->writeOutput();
    }

    private function run_(array $extra = [])
    {
        return $this->artisan('webgis:import', ['dir' => $this->dir, '--alt' => $this->alt] + $extra);
    }

    private function snapshot(): array
    {
        return [
            'shore' => $this->pg()->table('shorelines')->orderBy('source')->orderBy('year')->get(['id', 'year', 'source', 'water_index'])->map(fn ($r) => (array) $r)->all(),
            'lrr' => $this->pg()->table('transect_alt_lrr')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all(),
            'years' => $this->pg()->table('alt_source_years')->orderBy('source')->orderBy('year')->get()->map(fn ($r) => (array) $r)->all(),
        ];
    }

    public function test_imports_alt_shorelines_with_source_and_leaves_main_untouched(): void
    {
        $mainBefore = $this->pg()->table('shorelines')->where('source', 'main')->orderBy('id')->get(['id', 'year', 'water_index'])->map(fn ($r) => (array) $r)->all();
        $this->full();
        $this->run_()->expectsOutputToContain('alt_shorelines')->expectsOutputToContain('Import xong.')->assertSuccessful();

        $this->assertSame(2, $this->pg()->table('shorelines')->where('source', 's2c1')->count());
        $this->assertSame(2, $this->pg()->table('shorelines')->where('source', 'landsat')->count());
        $this->assertSame('MNDWI', $this->pg()->table('shorelines')->where('source', 'landsat')->where('year', 2022)->value('water_index'));
        $mainAfter = $this->pg()->table('shorelines')->where('source', 'main')->orderBy('id')->get(['id', 'year', 'water_index'])->map(fn ($r) => (array) $r)->all();
        $this->assertSame($mainBefore, $mainAfter);
    }

    public function test_utm_geometry_is_reprojected_to_wgs84(): void
    {
        $this->full();
        $this->run_()->assertSuccessful();
        $r = $this->pg()->selectOne("SELECT ST_X(ST_StartPoint(ST_GeometryN(geom, 1))) AS lng, ST_Y(ST_StartPoint(ST_GeometryN(geom, 1))) AS lat, ST_SRID(geom) AS srid, GeometryType(geom) AS t
            FROM shorelines WHERE source = 's2c1' AND year = 2020");
        $this->assertSame(4326, $r->srid);
        $this->assertSame('MULTILINESTRING', $r->t);
        $this->assertEqualsWithDelta(105.2, $r->lng, 0.01);
        $this->assertEqualsWithDelta(10.56, $r->lat, 0.02);
    }

    public function test_geometry_without_crs_block_is_taken_as_wgs84(): void
    {
        $this->shoreline('s2c1', 2020, 0, null);
        $json = json_decode(file_get_contents("{$this->alt}/s2c1/shoreline/2020_mndwi.json"), true);
        $json['features'][0]['geometry']['coordinates'] = [[[105.2, 10.5], [105.2, 10.6]]];
        file_put_contents("{$this->alt}/s2c1/shoreline/2020_mndwi.json", json_encode($json));
        $this->run_()->assertSuccessful();
        $this->assertEqualsWithDelta(105.2, $this->pg()->selectOne("SELECT ST_X(ST_StartPoint(ST_GeometryN(geom, 1))) AS x FROM shorelines WHERE source = 's2c1'")->x, 1e-6);
    }

    public function test_alt_lrr_is_stored_per_transect_and_source_and_unknown_codes_are_skipped(): void
    {
        $this->full();
        $this->run_()->assertSuccessful();

        $t01 = $this->pg()->table('transects')->where('transect_code', 'T01')->value('id');
        $row = $this->pg()->table('transect_alt_lrr')->where(['transect_id' => $t01, 'source' => 's2c1'])->first();
        $this->assertEqualsWithDelta(-2.5, $row->lrr_m_per_year, 1e-9);
        $this->assertSame('mild_erosion', $row->change_class);
        $this->assertEqualsWithDelta(0.4, $row->diff_vs_main, 1e-9);
        $t02 = $this->pg()->table('transects')->where('transect_code', 'T02')->value('id');
        $this->assertNull($this->pg()->table('transect_alt_lrr')->where(['transect_id' => $t02, 'source' => 's2c1'])->value('lrr_m_per_year'));
        $this->assertSame(4, $this->pg()->table('transect_alt_lrr')->count()); // 2 transect x 2 nguồn; KHONG_CO bị bỏ
        $this->assertSame(0, $this->pg()->table('transects')->where('transect_code', 'KHONG_CO')->count());
    }

    public function test_low_confidence_years_and_scene_counts_are_stored(): void
    {
        $this->full();
        $this->run_()->assertSuccessful();
        $low = $this->pg()->table('alt_source_years')->where(['source' => 's2c1', 'low_confidence' => true])->orderBy('year')->pluck('year')->all();
        $this->assertSame([2018, 2022], $low);
        $this->assertSame(10, (int) $this->pg()->table('alt_source_years')->where(['source' => 's2c1', 'year' => 2020])->value('scenes_used'));
        $this->assertSame(0, $this->pg()->table('alt_source_years')->where(['source' => 'landsat', 'low_confidence' => true])->count());
    }

    public function test_three_imports_give_identical_state(): void
    {
        $this->full();
        $this->run_()->assertSuccessful();
        $first = $this->snapshot();
        foreach ([1, 2] as $_) {
            $this->run_()->assertSuccessful();
        }
        $this->assertSame($first, $this->snapshot());
    }

    public function test_reimport_updates_values_in_place_without_new_rows(): void
    {
        $this->full();
        $this->run_()->assertSuccessful();
        $id = $this->pg()->table('shorelines')->where(['source' => 's2c1', 'year' => 2020])->value('id');
        $before = $this->pg()->selectOne("SELECT ST_X(ST_StartPoint(ST_GeometryN(geom, 1))) AS x FROM shorelines WHERE id = {$id}")->x;
        $this->shoreline('s2c1', 2020, 500);
        $this->writeOutput(-9.0);
        $this->run_()->assertSuccessful();
        $this->assertSame($id, $this->pg()->table('shorelines')->where(['source' => 's2c1', 'year' => 2020])->value('id'));
        $this->assertSame(4, $this->pg()->table('shorelines')->where('source', '<>', 'main')->count());
        $after = $this->pg()->selectOne("SELECT ST_X(ST_StartPoint(ST_GeometryN(geom, 1))) AS x FROM shorelines WHERE id = {$id}")->x;
        $this->assertGreaterThan($before, $after);
        $t01 = $this->pg()->table('transects')->where('transect_code', 'T01')->value('id');
        $this->assertEqualsWithDelta(-9.0, $this->pg()->table('transect_alt_lrr')->where(['transect_id' => $t01, 'source' => 's2c1'])->value('lrr_m_per_year'), 1e-9);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->full();
        $before = $this->snapshot();
        $this->run_(['--dry-run' => true])->expectsOutputToContain('Dry-run xong')->assertSuccessful();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_without_alt_option_alt_files_are_ignored(): void
    {
        $this->full();
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame(0, $this->pg()->table('shorelines')->where('source', '<>', 'main')->count());
        $this->assertSame(0, $this->pg()->table('transect_alt_lrr')->count());
    }

    public function test_missing_alt_directory_fails_before_touching_database(): void
    {
        $before = $this->snapshot();
        $this->artisan('webgis:import', ['dir' => $this->dir, '--alt' => '/khong/ton/tai'])->assertFailed();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_source_mismatch_in_file_rolls_everything_back(): void
    {
        $this->full();
        $this->shoreline('landsat', 2022, 90, 32648, 's2c1');
        $before = $this->snapshot();
        $this->run_()->assertFailed();
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, $this->pg()->table('transect_alt_lrr')->count());
    }

    public function test_main_shoreline_import_still_upserts_on_year_index_source(): void
    {
        $f = ['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[105.3, 10.5], [105.31, 10.5]]], 'properties' => ['year' => 2018, 'water_index' => 'MNDWI']];
        file_put_contents("{$this->dir}/shorelines.json", json_encode(['type' => 'FeatureCollection', 'features' => [$f]]));
        $id = $this->pg()->table('shorelines')->where(['year' => 2018, 'source' => 'main'])->value('id');
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->assertSame($id, $this->pg()->table('shorelines')->where(['year' => 2018, 'source' => 'main'])->value('id'));
        $this->assertSame(1, $this->pg()->table('shorelines')->where(['year' => 2018, 'water_index' => 'MNDWI'])->count());
    }
}
