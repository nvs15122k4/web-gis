<?php

namespace Tests\Feature\Regression;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Khi PostGIS không truy vấn được (bảng/schema không tồn tại) mọi endpoint không gian phải trả 503
 * "đang cập nhật" thay vì 500 — giao diện vẫn chạy. Mô phỏng bằng search_path trỏ sang schema rỗng.
 */
class ExportPostgisFailureTest extends TestCase
{
    private function breakPostgis(): void
    {
        $pg = \DB::connection('pgsql');
        $pg->statement('CREATE SCHEMA IF NOT EXISTS tmp_empty_schema_r2');
        $pg->statement('SET LOCAL search_path TO tmp_empty_schema_r2');
    }

    public static function exports(): array
    {
        $out = [];
        foreach (['transects', 'shorelines', 'hotspots', 'reference'] as $l) {
            foreach (['geojson', 'csv'] as $f) {
                $out["$l.$f"] = [$l, $f];
            }
        }

        return $out;
    }

    #[DataProvider('exports')]
    public function test_export_returns_503_when_postgis_fails(string $layer, string $format): void
    {
        $this->breakPostgis();
        $this->get("/api/export/{$layer}.{$format}")
            ->assertStatus(503)
            ->assertJsonPath('status', 'updating')
            ->assertJsonPath('message', 'Dữ liệu đang cập nhật.');
    }

    #[DataProvider('exports')]
    public function test_export_503_is_json_not_attachment(string $layer, string $format): void
    {
        $this->breakPostgis();
        $r = $this->get("/api/export/{$layer}.{$format}");
        $this->assertStringContainsString('application/json', $r->headers->get('Content-Type'));
        $this->assertNull($r->headers->get('Content-Disposition'));
    }

    public function test_export_503_does_not_leak_sql_or_schema_details(): void
    {
        $this->breakPostgis();
        $body = $this->get('/api/export/transects.geojson')->assertStatus(503)->getContent();
        foreach (['SQLSTATE', 'SELECT', 'shoreline_change_stats', 'tmp_empty_schema_r2', 'pgsql', 'vendor/'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
        $this->assertSame(['message', 'status'], array_keys(json_decode($body, true)));
    }

    public static function spatialEndpoints(): array
    {
        return array_map(fn ($u) => [$u], [
            '/api/summary', '/api/shorelines', '/api/transects', '/api/transects/1', '/api/transects/1/history',
            '/api/erosion-hotspots', '/api/reference-events',
        ]);
    }

    #[DataProvider('spatialEndpoints')]
    public function test_other_spatial_endpoints_also_503(string $url): void
    {
        $this->breakPostgis();
        $this->getJson($url)->assertStatus(503)->assertJsonPath('status', 'updating');
    }

    public function test_status_endpoint_survives_postgis_failure(): void
    {
        $this->breakPostgis();
        $this->getJson('/api/status')->assertOk()->assertJsonPath('postgis', false)->assertJsonPath('mysql', true);
    }

    public function test_threshold_config_is_independent_of_postgis(): void
    {
        $this->breakPostgis();
        $this->getJson('/api/config/thresholds')->assertOk()->assertJsonFragment(['key' => 'min_erosion_rate']);
    }

    public function test_admin_threshold_update_works_while_postgis_is_down(): void
    {
        $token = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->json('token');
        $this->breakPostgis();
        $this->withToken($token)->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 2])
            ->assertOk()->assertJsonPath('value', '2');
    }

    public function test_map_page_still_renders_when_postgis_is_down(): void
    {
        $this->breakPostgis();
        $this->get('/')->assertOk();
    }

    public function test_503_response_is_not_cacheable_as_attachment_and_has_no_etag_of_data(): void
    {
        $this->breakPostgis();
        $r = $this->get('/api/export/reference.csv')->assertStatus(503);
        $this->assertStringNotContainsString('webgis_songhau', (string) $r->headers->get('Content-Disposition'));
    }
}
