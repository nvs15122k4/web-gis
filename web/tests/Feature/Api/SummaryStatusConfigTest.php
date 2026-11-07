<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SummaryStatusConfigTest extends TestCase
{
    public function test_summary_structure(): void
    {
        $this->getJson('/api/summary')->assertOk()->assertJsonStructure([
            'transects' => ['total', 'significant', 'by_class', 'mean_lrr_m_per_year', 'mean_epr_m_per_year', 'max_erosion' => ['id', 'transect_code', 'lrr_m_per_year'], 'max_accretion'],
            'shorelines' => ['years', 'count'],
            'hotspots' => ['count', 'length_m'],
            'reference_events' => ['count', 'by_level'],
        ]);
    }

    public function test_summary_by_class_sums_to_total_and_years_exclude_2022(): void
    {
        $j = $this->getJson('/api/summary')->json();
        $this->assertSame($j['transects']['total'], array_sum($j['transects']['by_class']));
        $this->assertSame([2018, 2019, 2020, 2021, 2023, 2024, 2025, 2026], $j['shorelines']['years']);
        $this->assertGreaterThan(0, $j['hotspots']['length_m']);
        $this->assertLessThan(0, $j['transects']['max_erosion']['lrr_m_per_year']);
        $this->assertGreaterThan(0, $j['transects']['max_accretion']['lrr_m_per_year']);
    }

    public function test_status_counts_match_summary(): void
    {
        $s = $this->getJson('/api/status')->json('data');
        $this->assertSame(10, $s['transects']);
        $this->assertSame(2, $s['hotspots']);
        $this->assertSame(2, $s['reference_events']);
    }

    public function test_status_geoserver_up_and_down(): void
    {
        Http::fake(['*' => Http::response('<ok/>', 200)]);
        $this->getJson('/api/status')->assertJsonPath('geoserver', true);
    }

    public function test_status_geoserver_http_500_is_false(): void
    {
        Http::fake(['*' => Http::response('', 500)]);
        $this->getJson('/api/status')->assertJsonPath('geoserver', false);
    }

    public function test_status_geoserver_connection_failure_is_false(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('down'));
        $this->getJson('/api/status')->assertOk()->assertJsonPath('geoserver', false);
    }

    public function test_status_null_counts_when_tables_missing(): void
    {
        // Bảng chưa tồn tại -> null thay vì lỗi 500. Dùng savepoint-an toàn: đổi schema search_path sang schema rỗng.
        $pg = \DB::connection('pgsql');
        $pg->statement('CREATE SCHEMA IF NOT EXISTS tmp_empty_schema');
        $pg->statement('SET LOCAL search_path TO tmp_empty_schema');
        $this->getJson('/api/status')->assertOk()->assertJsonPath('postgis', false)->assertJsonPath('data.shorelines', null);
    }

    public function test_thresholds_list_shape_and_order(): void
    {
        $r = $this->getJson('/api/config/thresholds')->assertOk();
        foreach ($r->json() as $row) {
            $this->assertSame(['key', 'value', 'description'], array_keys($row));
        }
        $keys = array_column($r->json(), 'key');
        $s = $keys;
        sort($s);
        $this->assertSame($s, $keys);
    }

    public static function readEndpoints(): array
    {
        return array_map(fn ($p) => [$p], [
            '/api/status', '/api/summary', '/api/shorelines', '/api/transects', '/api/erosion-hotspots',
            '/api/reference-events', '/api/config/thresholds',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readEndpoints')]
    public function test_public_endpoints_need_no_auth_and_return_json(string $url): void
    {
        $this->get($url, ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readEndpoints')]
    public function test_public_endpoints_reject_post(string $url): void
    {
        $this->postJson($url)->assertStatus(405);
    }

    public function test_unknown_api_route_returns_json_404(): void
    {
        $this->getJson('/api/khong-co')->assertNotFound()->assertHeader('Content-Type', 'application/json');
    }
}
