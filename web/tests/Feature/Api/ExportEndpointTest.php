<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ExportEndpointTest extends TestCase
{
    public static function layers(): array
    {
        return [
            'transects' => ['transects', 10, 'LineString'],
            'shorelines' => ['shorelines', 8, 'MultiLineString'],
            'hotspots' => ['hotspots', 2, 'LineString'],
            'reference' => ['reference', 2, 'Point'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('layers')]
    public function test_geojson_export(string $layer, int $count, string $geomType): void
    {
        $r = $this->get("/api/export/{$layer}.geojson")->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json')
            ->assertHeader('Content-Disposition', "attachment; filename=\"webgis_songhau_{$layer}.geojson\"");
        $fc = json_decode($r->getContent(), true);
        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertCount($count, $fc['features']);
        foreach ($fc['features'] as $f) {
            $this->assertSame($geomType, $f['geometry']['type']);
            $this->assertArrayNotHasKey('geom', $f['properties']);
            $this->assertArrayHasKey('id', $f);
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('layers')]
    public function test_csv_export(string $layer, int $count): void
    {
        $r = $this->get("/api/export/{$layer}.csv")->assertOk()
            ->assertHeader('Content-Disposition', "attachment; filename=\"webgis_songhau_{$layer}.csv\"");
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));
        $body = $r->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $lines = array_values(array_filter(explode("\n", substr($body, 3))));
        $this->assertCount($count + 1, $lines);
        $this->assertStringEndsWith(',geom', trim($lines[0]));
        $this->assertMatchesRegularExpression('/(LINESTRING|MULTILINESTRING|POINT)\(/', $lines[1]);
    }

    public static function invalid(): array
    {
        return [['users.csv'], ['transects.shp'], ['transects.xml'], ['transects'], ['baseline.geojson'], ['TRANSECTS.geojson'], ['..%2Fenv.csv']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function test_invalid_layer_or_format_404(string $path): void
    {
        $this->get('/api/export/'.$path)->assertNotFound();
    }

    public function test_transects_csv_contains_stats_columns(): void
    {
        $header = explode(',', trim(explode("\n", substr($this->get('/api/export/transects.csv')->getContent(), 3))[0]));
        foreach (['transect_code', 'change_class', 'lrr_m_per_year', 'is_significant', 'geom'] as $c) {
            $this->assertContains($c, $header);
        }
    }

    public function test_csv_boolean_rendered_as_text(): void
    {
        $body = $this->get('/api/export/transects.csv')->getContent();
        $this->assertStringContainsString('true', $body);
        $this->assertStringContainsString('false', $body);
    }

    public function test_csv_keeps_vietnamese_text(): void
    {
        $this->assertStringContainsString('Ấp Bình Hòa', $this->get('/api/export/reference.csv')->getContent());
    }

    public function test_geojson_keeps_unicode_unescaped(): void
    {
        $this->assertStringContainsString('Sạt lở', $this->get('/api/export/reference.geojson')->getContent());
    }

    public function test_empty_layer_csv_is_bom_only_and_geojson_has_no_features(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->delete();
        $this->assertSame("\xEF\xBB\xBF", $this->get('/api/export/hotspots.csv')->getContent());
        $this->assertSame([], json_decode($this->get('/api/export/hotspots.geojson')->getContent(), true)['features']);
    }

    public function test_transects_sorted_by_code(): void
    {
        $codes = array_column(array_column(json_decode($this->get('/api/export/transects.geojson')->getContent(), true)['features'], 'properties'), 'transect_code');
        $s = $codes;
        sort($s);
        $this->assertSame($s, $codes);
    }
}
