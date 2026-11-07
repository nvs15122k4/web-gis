<?php

namespace Tests\Unit;

use App\Models\ErosionHotspot;
use App\Models\Shoreline;
use App\Models\Transect;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;

class HasGeoJsonGeometryTest extends BaseUnitTest
{
    private function model(string $class, array $attrs): object
    {
        return (new $class)->forceFill($attrs);
    }

    public function test_collection_rong_tra_feature_collection_rong(): void
    {
        $this->assertSame(['type' => 'FeatureCollection', 'features' => []], Shoreline::toFeatureCollection(new Collection));
    }

    public function test_feature_co_id_geometry_va_properties(): void
    {
        $m = $this->model(Shoreline::class, [
            'id' => 7, 'year' => 2020, 'water_index' => 'MNDWI',
            'geom' => '0101...', 'geojson' => '{"type":"Point","coordinates":[106.1,9.5]}',
        ]);
        $fc = Shoreline::toFeatureCollection(new Collection([$m]));

        $this->assertSame('FeatureCollection', $fc['type']);
        $f = $fc['features'][0];
        $this->assertSame('Feature', $f['type']);
        $this->assertSame(7, $f['id']);
        $this->assertSame(['type' => 'Point', 'coordinates' => [106.1, 9.5]], $f['geometry']);
        $this->assertSame(['id' => 7, 'year' => 2020, 'water_index' => 'MNDWI'], $f['properties']);
    }

    public function test_properties_loai_bo_geom_va_geojson(): void
    {
        $m = $this->model(Transect::class, ['id' => 1, 'geom' => 'x', 'geojson' => '{"type":"Point","coordinates":[0,0]}', 'transect_code' => 'T1']);
        $props = Transect::toFeatureCollection(new Collection([$m]))['features'][0]['properties'];
        $this->assertArrayNotHasKey('geom', $props);
        $this->assertArrayNotHasKey('geojson', $props);
        $this->assertSame('T1', $props['transect_code']);
    }

    public static function geojsonStrings(): array
    {
        return [
            'null' => [null, null],
            'json null' => ['null', null],
            'polygon' => ['{"type":"Polygon","coordinates":[[[0,0],[1,0],[1,1],[0,0]]]}', ['type' => 'Polygon', 'coordinates' => [[[0, 0], [1, 0], [1, 1], [0, 0]]]]],
            'multiline' => ['{"type":"MultiLineString","coordinates":[[[0,0],[1,1]]]}', ['type' => 'MultiLineString', 'coordinates' => [[[0, 0], [1, 1]]]]],
        ];
    }

    #[DataProvider('geojsonStrings')]
    public function test_geometry_giai_ma_tu_chuoi_geojson(?string $raw, ?array $expected): void
    {
        $m = $this->model(ErosionHotspot::class, ['id' => 1, 'geojson' => $raw]);
        $this->assertSame($expected, ErosionHotspot::toFeatureCollection(new Collection([$m]))['features'][0]['geometry']);
    }

    public function test_thu_tu_va_chi_so_feature_duoc_giu_lien_tuc(): void
    {
        $items = new Collection([
            3 => $this->model(Shoreline::class, ['id' => 30, 'geojson' => 'null']),
            9 => $this->model(Shoreline::class, ['id' => 10, 'geojson' => 'null']),
        ]);
        $features = Shoreline::toFeatureCollection($items)['features'];
        $this->assertSame([0, 1], array_keys($features));
        $this->assertSame([30, 10], array_column($features, 'id'));
    }

    public function test_to_feature_collection_ma_hoa_json_duoc(): void
    {
        $m = $this->model(Shoreline::class, ['id' => 1, 'ghi_chu' => 'Sông Hậu', 'geojson' => 'null']);
        $json = json_encode(Shoreline::toFeatureCollection(new Collection([$m])), JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Sông Hậu', $json);
    }

    public static function models(): array
    {
        return [[Shoreline::class, 'shorelines'], [Transect::class, 'transects'], [ErosionHotspot::class, 'erosion_hotspots']];
    }

    #[DataProvider('models')]
    public function test_scope_with_geojson_them_cot_geojson(string $class, string $table): void
    {
        $sql = $class::withGeoJson()->toSql();
        $this->assertStringContainsString("ST_AsGeoJSON({$table}.geom, 6) AS geojson", $sql);
        $this->assertStringContainsString("\"{$table}\".*", $sql);
    }

    public function test_scope_with_geojson_nhan_ten_cot_tuy_chon(): void
    {
        $this->assertStringContainsString('ST_AsGeoJSON(shorelines.geom_4326, 6)', Shoreline::withGeoJson('geom_4326')->toSql());
    }
}
