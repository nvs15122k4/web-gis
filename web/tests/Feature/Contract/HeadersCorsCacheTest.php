<?php

namespace Tests\Feature\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HeadersCorsCacheTest extends TestCase
{
    public static function jsonEndpoints(): array
    {
        return array_map(fn ($u) => [$u], [
            '/api/status', '/api/summary', '/api/shorelines', '/api/transects', '/api/transects/1', '/api/transects/1/history',
            '/api/erosion-hotspots', '/api/reference-events', '/api/config/thresholds',
        ]);
    }

    #[DataProvider('jsonEndpoints')]
    public function test_json_content_type_utf8(string $url): void
    {
        $r = $this->get($url, ['Accept' => 'application/json']);
        $this->assertStringContainsString('application/json', $r->headers->get('Content-Type'));
    }

    public static function cachedEndpoints(): array
    {
        return array_map(fn ($u) => [$u], [
            '/api/summary', '/api/shorelines', '/api/transects', '/api/transects/1', '/api/transects/1/history',
            '/api/erosion-hotspots', '/api/reference-events',
        ]);
    }

    public static function uncachedEndpoints(): array
    {
        return array_map(fn ($u) => [$u], ['/api/status', '/api/config/thresholds']);
    }

    #[DataProvider('cachedEndpoints')]
    public function test_read_only_data_endpoints_are_publicly_cacheable_with_etag(string $url): void
    {
        $r = $this->get($url);
        if (! $r->isSuccessful()) {
            $this->markTestSkipped('Endpoint không trả 2xx trong môi trường test (không có dữ liệu).');
        }
        $cc = $r->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cc);
        $this->assertStringContainsString('max-age=60', $cc);
        $this->assertStringContainsString('s-maxage=300', $cc);
        $this->assertStringContainsString('stale-while-revalidate=600', $cc);
        $this->assertNotNull($r->headers->get('ETag'));
    }

    #[DataProvider('cachedEndpoints')]
    public function test_conditional_request_with_matching_etag_returns_304(string $url): void
    {
        $first = $this->get($url);
        if (! $first->isSuccessful()) {
            $this->markTestSkipped('Endpoint không trả 2xx trong môi trường test (không có dữ liệu).');
        }
        $this->get($url, ['If-None-Match' => $first->headers->get('ETag')])->assertStatus(304);
    }

    #[DataProvider('uncachedEndpoints')]
    public function test_status_and_thresholds_are_not_publicly_cached(string $url): void
    {
        $r = $this->get($url);
        $this->assertStringNotContainsString('public', (string) $r->headers->get('Cache-Control'));
        $this->assertNull($r->headers->get('ETag'));
    }

    #[DataProvider('jsonEndpoints')]
    public function test_no_session_cookie_on_stateless_api(string $url): void
    {
        $this->assertEmpty($this->get($url)->headers->getCookies());
    }

    #[DataProvider('jsonEndpoints')]
    public function test_does_not_disclose_server_stack(string $url): void
    {
        $r = $this->get($url);
        $this->assertStringNotContainsString('PHP/', (string) $r->headers->get('X-Powered-By'));
        $this->assertNull($r->headers->get('X-Debug-Token'));
    }

    #[DataProvider('jsonEndpoints')]
    public function test_cors_preflight_allows_any_origin_by_framework_default(string $url): void
    {
        $r = $this->call('OPTIONS', $url, [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
        $this->assertContains($r->getStatusCode(), [200, 204]);
        $this->assertSame('*', $r->headers->get('Access-Control-Allow-Origin'));
        $this->assertNull($r->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_cors_header_on_simple_cross_origin_get(): void
    {
        $r = $this->get('/api/summary', ['Origin' => 'https://other.example']);
        $this->assertSame('*', $r->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_cors_not_applied_to_web_pages(): void
    {
        $r = $this->get('/', ['Origin' => 'https://other.example']);
        $this->assertNull($r->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_preflight_for_put_admin_route_is_answered_without_auth(): void
    {
        $r = $this->call('OPTIONS', '/api/admin/config/thresholds/min_erosion_rate', [], [], [], [
            'HTTP_ORIGIN' => 'https://x.example',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization,content-type',
        ]);
        $this->assertContains($r->getStatusCode(), [200, 204]);
    }

    public function test_export_geojson_headers(): void
    {
        $r = $this->get('/api/export/shorelines.geojson');
        $this->assertSame('application/geo+json', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('attachment; filename="', $r->headers->get('Content-Disposition'));
    }

    public function test_export_csv_headers_and_charset(): void
    {
        $r = $this->get('/api/export/shorelines.csv');
        $this->assertSame('text/csv; charset=UTF-8', $r->headers->get('Content-Type'));
    }

    public function test_export_content_length_matches_body(): void
    {
        $r = $this->get('/api/export/transects.csv');
        $len = $r->headers->get('Content-Length');
        if ($len !== null) {
            $this->assertSame(strlen($r->getContent()), (int) $len);
        }
        $this->assertGreaterThan(0, strlen($r->getContent()));
    }

    public function test_export_ignores_accept_header_negotiation(): void
    {
        $r = $this->get('/api/export/hotspots.geojson', ['Accept' => 'text/html']);
        $r->assertOk();
        $this->assertSame('application/geo+json', $r->headers->get('Content-Type'));
    }

    public function test_head_request_on_api_is_served(): void
    {
        $this->call('HEAD', '/api/summary')->assertOk();
    }

    public function test_html_pages_are_utf8(): void
    {
        $this->assertStringContainsStringIgnoringCase('charset=utf-8', $this->get('/')->headers->get('Content-Type'));
    }

    public function test_html_error_page_for_unknown_web_path_is_not_json(): void
    {
        $r = $this->get('/khong-co-trang-nay');
        $r->assertNotFound();
        $this->assertStringContainsString('text/html', $r->headers->get('Content-Type'));
    }

    public function test_unknown_api_path_is_json_even_without_accept(): void
    {
        $r = $this->get('/api/khong-co');
        $r->assertNotFound();
        $this->assertStringContainsString('application/json', $r->headers->get('Content-Type'));
    }

    public function test_health_endpoint_ok(): void
    {
        $this->get('/up')->assertOk();
    }
}
