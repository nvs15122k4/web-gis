<?php

namespace Tests\Feature\Performance;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Kích thước phản hồi, số truy vấn, không phân trang — mô tả hành vi hiện tại. */
class ResponseSizeAndQueriesTest extends TestCase
{
    private function seedMoreTransects(int $n): void
    {
        $pg = \DB::connection('pgsql');
        $b = $pg->table('baseline')->value('id');
        for ($i = 0; $i < $n; $i++) {
            $lat = 10.7 + $i * 0.0005;
            $id = $pg->selectOne("INSERT INTO transects (transect_code, baseline_id, geom) VALUES (?, ?, ST_GeomFromText(?, 4326)) RETURNING id",
                ['P'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), $b, sprintf('LINESTRING(105.2 %F, 105.21 %F)', $lat, $lat)])->id;
            $pg->table('shoreline_change_stats')->insert(['transect_id' => $id, 'nsm_m' => -1, 'epr_m_per_year' => -0.1, 'lrr_m_per_year' => -0.1,
                'change_class' => 'stable', 'is_significant' => false, 'consecutive_erosion_periods' => 0]);
        }
    }

    private function queryCount(callable $fn): int
    {
        foreach (['mysql', 'pgsql'] as $c) {
            \DB::connection($c)->flushQueryLog();
            \DB::connection($c)->enableQueryLog();
        }
        $fn();
        $n = count(\DB::connection('mysql')->getQueryLog()) + count(\DB::connection('pgsql')->getQueryLog());
        foreach (['mysql', 'pgsql'] as $c) {
            \DB::connection($c)->disableQueryLog();
        }

        return $n;
    }

    public static function endpoints(): array
    {
        return [
            'status' => ['/api/status', 20_000],
            'summary' => ['/api/summary', 5_000],
            'shorelines' => ['/api/shorelines', 30_000],
            'transects' => ['/api/transects', 30_000],
            'hotspots' => ['/api/erosion-hotspots', 5_000],
            'reference' => ['/api/reference-events', 5_000],
            'thresholds' => ['/api/config/thresholds', 2_000],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_fixture_payload_is_small(string $url, int $maxBytes): void
    {
        $this->assertLessThan($maxBytes, strlen($this->getJson($url)->getContent()));
    }

    #[DataProvider('endpoints')]
    public function test_fixture_response_is_fast(string $url): void
    {
        $this->getJson($url); // làm nóng
        $t = microtime(true);
        $this->getJson($url);
        $this->assertLessThan(2.0, microtime(true) - $t);
    }

    public static function queryBudgets(): array
    {
        return [
            ['/api/summary', 7], // by_class, agg, 2 extreme, years, hotspots, reference
            ['/api/transects', 1],
            ['/api/shorelines', 1],
            ['/api/erosion-hotspots', 1],
            ['/api/reference-events', 1],
            ['/api/config/thresholds', 1],
            ['/api/transects/1', 2],
            ['/api/transects/1/history', 2],
        ];
    }

    #[DataProvider('queryBudgets')]
    public function test_query_count_within_budget(string $url, int $max): void
    {
        $n = $this->queryCount(fn () => $this->getJson($url));
        $this->assertLessThanOrEqual($max, $n, "{$url} dùng {$n} truy vấn");
    }

    public function test_transect_list_has_no_n_plus_one(): void
    {
        $a = $this->queryCount(fn () => $this->getJson('/api/transects')->assertOk());
        $this->seedMoreTransects(100);
        $b = $this->queryCount(fn () => $this->getJson('/api/transects')->assertOk());
        $this->assertSame($a, $b);
    }

    public function test_no_pagination_all_rows_returned(): void
    {
        $this->seedMoreTransects(300);
        $r = $this->getJson('/api/transects')->assertOk();
        $this->assertCount(310, $r->json('features'));
        $this->assertArrayNotHasKey('links', $r->json());
        $this->assertArrayNotHasKey('meta', $r->json());
        $this->assertNull($r->headers->get('Link'));
    }

    public function test_pagination_params_are_ignored(): void
    {
        $this->seedMoreTransects(30);
        $this->assertCount(40, $this->getJson('/api/transects?page=2&per_page=5&limit=3&offset=2')->json('features'));
    }

    public function test_payload_grows_linearly_and_stays_reasonable_at_310_transects(): void
    {
        $small = strlen($this->getJson('/api/transects')->getContent());
        $this->seedMoreTransects(300);
        $big = strlen($this->getJson('/api/transects')->getContent());
        $this->assertGreaterThan($small * 10, $big);
        $this->assertLessThan(400_000, $big);
    }

    public function test_export_scales_and_csv_smaller_than_geojson(): void
    {
        $this->seedMoreTransects(200);
        $csv = strlen($this->get('/api/export/transects.csv')->getContent());
        $geo = strlen($this->get('/api/export/transects.geojson')->getContent());
        $this->assertLessThan($geo, $csv);
        $this->assertCount(210, array_filter(explode("\n", substr($this->get('/api/export/transects.csv')->getContent(), 3)))
            ? array_slice(array_filter(explode("\n", substr($this->get('/api/export/transects.csv')->getContent(), 3))), 1) : []);
    }

    public function test_export_uses_constant_query_count(): void
    {
        $this->seedMoreTransects(100);
        $this->assertSame(1, $this->queryCount(fn () => $this->get('/api/export/transects.geojson')->assertOk()));
    }

    public function test_bbox_filter_reduces_payload(): void
    {
        $this->seedMoreTransects(100);
        $all = strlen($this->getJson('/api/transects')->getContent());
        $some = strlen($this->getJson('/api/transects?bbox=105.1,10.5,105.3,10.6')->getContent());
        $this->assertLessThan($all, $some);
    }

    public function test_geometry_precision_is_six_decimals(): void
    {
        $body = $this->getJson('/api/transects')->getContent();
        $this->assertDoesNotMatchRegularExpression('/\d+\.\d{7,}/', json_encode(array_column($this->getJson('/api/transects')->json('features'), 'geometry')));
        $this->assertNotEmpty($body);
    }

    public function test_response_is_not_gzip_by_app_layer(): void
    {
        $this->assertNull($this->getJson('/api/transects', ['Accept-Encoding' => 'gzip'])->headers->get('Content-Encoding'));
    }

    public function test_history_payload_bounded_by_years(): void
    {
        $id = $this->getJson('/api/transects')->json('features.0.id');
        $this->assertLessThanOrEqual(9, count($this->getJson("/api/transects/{$id}/history")->json()));
    }
}
