<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** webgis:import --alt: ưu tiên đường bờ QGIS (cùng phương pháp với bản chính), rơi về bản Python khi năm đó chưa có. */
class ImportAltQgisPreferenceTest extends TestCase
{
    private string $dir;

    private string $alt;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webgis_qp_out_'.uniqid();
        $this->alt = sys_get_temp_dir().'/webgis_qp_alt_'.uniqid();
        mkdir($this->dir);
        foreach (['s2c1', 'landsat'] as $s) {
            mkdir("{$this->alt}/{$s}/shoreline", 0777, true);
            mkdir("{$this->alt}/{$s}/qgis_shoreline", 0777, true);
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

    private function line(string $path, int $year, string $source, float $lng): void
    {
        file_put_contents($path, json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => ['year' => $year, 'source' => $source],
            'geometry' => ['type' => 'MultiLineString', 'coordinates' => [[[$lng, 10.5], [$lng, 10.6]]]],
        ]]]));
    }

    private function python(string $s, int $year, float $lng): void
    {
        $this->line("{$this->alt}/{$s}/shoreline/{$year}_mndwi.json", $year, $s, $lng);
    }

    private function qgis(string $s, int $year, float $lng, ?string $src = null): void
    {
        $this->line("{$this->alt}/{$s}/qgis_shoreline/{$year}.json", $year, $src ?? "qgis_{$s}", $lng);
    }

    private function lng(string $source, int $year): float
    {
        return (float) DB::connection('pgsql')->selectOne(
            'SELECT ST_X(ST_StartPoint(ST_GeometryN(geom, 1))) AS x FROM shorelines WHERE source = ? AND year = ?', [$source, $year]
        )->x;
    }

    private function run_()
    {
        return $this->artisan('webgis:import', ['dir' => $this->dir, '--alt' => $this->alt]);
    }

    public function test_qgis_line_is_preferred_over_python_for_the_same_year(): void
    {
        $this->python('s2c1', 2020, 105.20);
        $this->qgis('s2c1', 2020, 105.25);
        $this->run_()->assertSuccessful();

        $this->assertEqualsWithDelta(105.25, $this->lng('s2c1', 2020), 1e-6);
    }

    public function test_year_without_a_qgis_file_falls_back_to_python(): void
    {
        $this->python('landsat', 2020, 105.20);
        $this->python('landsat', 2021, 105.21);
        $this->qgis('landsat', 2020, 105.26);
        $this->run_()->assertSuccessful();

        $this->assertEqualsWithDelta(105.26, $this->lng('landsat', 2020), 1e-6);
        $this->assertEqualsWithDelta(105.21, $this->lng('landsat', 2021), 1e-6, 'năm 2021 chưa có file QGIS: dùng bản Python');
    }

    public function test_without_any_qgis_directory_content_python_lines_are_imported_as_before(): void
    {
        $this->python('s2c1', 2020, 105.20);
        $this->run_()->assertSuccessful();

        $this->assertEqualsWithDelta(105.20, $this->lng('s2c1', 2020), 1e-6);
    }

    public function test_qgis_file_of_another_source_is_rejected_and_nothing_is_written(): void
    {
        $this->python('s2c1', 2020, 105.20);
        $this->qgis('s2c1', 2020, 105.25, 'qgis_landsat');   // nhầm nguồn
        $before = DB::connection('pgsql')->table('shorelines')->where('source', 's2c1')->count();

        $this->run_()->expectsOutputToContain('Import thất bại')->assertFailed();
        $this->assertSame($before, DB::connection('pgsql')->table('shorelines')->where('source', 's2c1')->count());
    }

    public function test_reimport_is_idempotent_with_qgis_lines(): void
    {
        $this->qgis('s2c1', 2020, 105.25);
        $this->python('s2c1', 2020, 105.20);
        $this->run_()->assertSuccessful();
        $this->run_()->assertSuccessful();

        $this->assertSame(1, DB::connection('pgsql')->table('shorelines')->where('source', 's2c1')->where('year', 2020)->count());
        $this->assertEqualsWithDelta(105.25, $this->lng('s2c1', 2020), 1e-6);
    }
}
