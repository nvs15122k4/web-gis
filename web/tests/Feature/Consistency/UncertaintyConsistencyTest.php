<?php

namespace Tests\Feature\Consistency;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** unc_lrr (độ bất định 95% của LRR) phải nhất quán giữa /api/transects/{id} và các định dạng xuất. */
class UncertaintyConsistencyTest extends TestCase
{
    private function pg()
    {
        return DB::connection('pgsql');
    }

    /** @return array{0:int,1:int} [id có unc_lrr=1.4, id có unc_lrr=null] */
    private function prepare(): array
    {
        $ids = $this->pg()->table('transects')->orderBy('transect_code')->limit(2)->pluck('id')->all();
        $this->pg()->table('shoreline_change_stats')->where('transect_id', $ids[0])->update(['unc_lrr' => 1.4]);
        $this->pg()->table('shoreline_change_stats')->where('transect_id', $ids[1])->update(['unc_lrr' => null]);

        return [$ids[0], $ids[1]];
    }

    private function geojsonProps(): array
    {
        $f = json_decode($this->get('/api/export/transects.geojson')->assertOk()->getContent(), true)['features'];

        return array_column(array_column($f, 'properties'), null, 'id');
    }

    private function csvRows(): array
    {
        $text = ltrim($this->get('/api/export/transects.csv')->assertOk()->getContent(), "\xEF\xBB\xBF");
        $lines = array_map(fn ($l) => str_getcsv($l, escape: ''), array_filter(explode("\n", $text), 'strlen'));
        $head = array_shift($lines);

        return array_column(array_map(fn ($l) => array_combine($head, $l), $lines), null, 'id');
    }

    public function test_unc_lrr_matches_between_api_and_geojson_export(): void
    {
        [$withUnc] = $this->prepare();

        $api = $this->getJson("/api/transects/{$withUnc}")->assertOk()->json('stats.unc_lrr');

        $this->assertEqualsWithDelta(1.4, $api, 1e-9);
        $this->assertEqualsWithDelta($api, $this->geojsonProps()[$withUnc]['unc_lrr'], 1e-9);
    }

    public function test_unc_lrr_matches_between_api_and_csv_export(): void
    {
        [$withUnc] = $this->prepare();

        $api = $this->getJson("/api/transects/{$withUnc}")->json('stats.unc_lrr');

        $this->assertEqualsWithDelta($api, (float) $this->csvRows()[$withUnc]['unc_lrr'], 1e-9);
    }

    public function test_null_unc_lrr_is_exported_as_null_everywhere(): void
    {
        [, $nullId] = $this->prepare();

        $this->assertNull($this->getJson("/api/transects/{$nullId}")->assertOk()->json('stats.unc_lrr'));
        $props = $this->geojsonProps()[$nullId];
        $this->assertArrayHasKey('unc_lrr', $props);
        $this->assertNull($props['unc_lrr']);
        $this->assertSame('', $this->csvRows()[$nullId]['unc_lrr'], 'CSV: null xuất ô rỗng, không phải "0" hay "null"');
    }

    public function test_every_transect_unc_lrr_agrees_between_api_and_geojson(): void
    {
        $props = $this->geojsonProps();
        $this->assertNotEmpty($props);

        foreach ($props as $id => $p) {
            $api = $this->getJson("/api/transects/{$id}")->json('stats.unc_lrr');
            if ($p['unc_lrr'] === null) {
                $this->assertNull($api, "transect {$id}");
            } else {
                $this->assertEqualsWithDelta($p['unc_lrr'], $api, 1e-9, "transect {$id}");
            }
        }
    }

    public function test_transects_layer_lrr_agrees_with_export(): void
    {
        $this->prepare();

        $features = $this->getJson('/api/transects')->assertOk()->json('features');
        $this->assertNotEmpty($features);
        $export = $this->geojsonProps();
        foreach ($features as $f) {
            $id = $f['properties']['id'] ?? $f['id'];
            $this->assertEqualsWithDelta($export[$id]['lrr_m_per_year'], $f['properties']['lrr_m_per_year'], 1e-9);
        }
    }
}
