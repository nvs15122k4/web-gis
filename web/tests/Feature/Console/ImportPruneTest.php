<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** webgis:import --prune: xoá transect không còn trong output mới; mặc định giữ nguyên hành vi cũ. */
class ImportPruneTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/webgis_prune_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->dir}/*") ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function pg()
    {
        return DB::connection('pgsql');
    }

    /** @param  list<string>  $codes */
    private function writeOutput(array $codes): void
    {
        file_put_contents("{$this->dir}/baseline.json", json_encode(['type' => 'FeatureCollection', 'features' => [[
            'type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'LineString', 'coordinates' => [[105.2, 10.5], [105.3, 10.55]]],
        ]]]));
        $features = [];
        foreach ($codes as $i => $code) {
            $features[] = ['type' => 'Feature', 'geometry' => ['type' => 'LineString', 'coordinates' => [[105.2 + $i * 0.001, 10.5], [105.21 + $i * 0.001, 10.51]]],
                'properties' => ['transect_code' => $code, 'distances' => ['2018' => 100.0, '2020' => 95.0],
                    'stats' => ['nsm_m' => -5.0, 'epr_m_per_year' => -2.5, 'lrr_m_per_year' => -2.5, 'se_lrr' => 0.1, 'unc_lrr' => 0.3, 'r_squared' => 0.9,
                        'is_significant' => true, 'change_class' => 'mild_erosion', 'consecutive_erosion_periods' => 1, 'u_shoreline_avg' => 20.0]]];
        }
        file_put_contents("{$this->dir}/transects.json", json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    private function has(string $code): bool
    {
        return $this->pg()->table('transects')->where('transect_code', $code)->exists();
    }

    public function test_without_prune_a_transect_missing_from_new_output_is_kept(): void
    {
        $this->writeOutput(['ZP_A', 'ZP_B']);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->writeOutput(['ZP_A']);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();

        $this->assertTrue($this->has('ZP_A'));
        $this->assertTrue($this->has('ZP_B'), 'mặc định không xoá: hành vi cũ giữ nguyên');
    }

    public function test_prune_removes_missing_transect_and_its_children_but_keeps_present_ones(): void
    {
        $this->writeOutput(['ZP_A', 'ZP_B']);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $idB = $this->pg()->table('transects')->where('transect_code', 'ZP_B')->value('id');
        $this->assertGreaterThan(0, $this->pg()->table('transect_shoreline_distances')->where('transect_id', $idB)->count());

        $this->writeOutput(['ZP_A']);
        $this->artisan('webgis:import', ['dir' => $this->dir, '--prune' => true])->expectsOutputToContain('pruned')->assertSuccessful();

        $this->assertTrue($this->has('ZP_A'));
        $this->assertFalse($this->has('ZP_B'));
        $this->assertSame(0, $this->pg()->table('transect_shoreline_distances')->where('transect_id', $idB)->count());
        $this->assertSame(0, $this->pg()->table('shoreline_change_stats')->where('transect_id', $idB)->count());
    }

    public function test_prune_keeps_the_same_id_for_a_transect_that_stays(): void
    {
        $this->writeOutput(['ZP_A', 'ZP_B']);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $idA = $this->pg()->table('transects')->where('transect_code', 'ZP_A')->value('id');
        $this->writeOutput(['ZP_A']);
        $this->artisan('webgis:import', ['dir' => $this->dir, '--prune' => true])->assertSuccessful();

        $this->assertSame($idA, $this->pg()->table('transects')->where('transect_code', 'ZP_A')->value('id'));
    }

    public function test_prune_never_deletes_everything_when_output_has_no_transects(): void
    {
        $before = $this->pg()->table('transects')->count();
        file_put_contents("{$this->dir}/baseline.json", json_encode(['type' => 'FeatureCollection', 'features' => []]));
        file_put_contents("{$this->dir}/transects.json", json_encode(['type' => 'FeatureCollection', 'features' => []]));
        $this->artisan('webgis:import', ['dir' => $this->dir, '--prune' => true])->assertSuccessful();

        $this->assertSame($before, $this->pg()->table('transects')->count());
    }

    public function test_dry_run_with_prune_deletes_nothing(): void
    {
        $this->writeOutput(['ZP_A', 'ZP_B']);
        $this->artisan('webgis:import', ['dir' => $this->dir])->assertSuccessful();
        $this->writeOutput(['ZP_A']);
        $this->artisan('webgis:import', ['dir' => $this->dir, '--prune' => true, '--dry-run' => true])->assertSuccessful();

        $this->assertTrue($this->has('ZP_B'));
    }
}
