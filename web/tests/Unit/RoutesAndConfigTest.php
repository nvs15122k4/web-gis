<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;

class RoutesAndConfigTest extends BaseUnitTest
{
    private function route(string $method, string $uri)
    {
        foreach ($this->app['router']->getRoutes() as $r) {
            if ($r->uri() === $uri && in_array($method, $r->methods(), true)) {
                return $r;
            }
        }

        return null;
    }

    public static function publicRoutes(): array
    {
        return [
            ['GET', 'api/status'], ['GET', 'api/summary'], ['GET', 'api/shorelines'], ['GET', 'api/transects'],
            ['GET', 'api/transects/{id}'], ['GET', 'api/transects/{id}/history'], ['GET', 'api/erosion-hotspots'],
            ['GET', 'api/reference-events'], ['GET', 'api/config/thresholds'], ['POST', 'api/auth/login'],
        ];
    }

    #[DataProvider('publicRoutes')]
    public function test_route_cong_khai_khong_co_middleware_auth(string $method, string $uri): void
    {
        $r = $this->route($method, $uri);
        $this->assertNotNull($r, "$method $uri");
        $this->assertNotContains('admin', $r->gatherMiddleware());
        $this->assertNotContains('auth:sanctum', $r->gatherMiddleware());
    }

    public function test_route_cap_nhat_nguong_yeu_cau_sanctum_va_admin(): void
    {
        $r = $this->route('PUT', 'api/admin/config/thresholds/{key}');
        $this->assertNotNull($r);
        $this->assertContains('auth:sanctum', $r->gatherMiddleware());
        $this->assertContains('admin', $r->gatherMiddleware());
    }

    public static function numericRoutes(): array
    {
        return [['api/transects/{id}'], ['api/transects/{id}/history']];
    }

    #[DataProvider('numericRoutes')]
    public function test_id_phai_la_so(string $uri): void
    {
        $r = $this->route('GET', $uri);
        $this->assertSame('[0-9]{1,9}', $r->wheres['id']);
    }

    public function test_route_chi_cho_phep_dung_phuong_thuc(): void
    {
        $this->assertNull($this->route('POST', 'api/status'));
        $this->assertNull($this->route('DELETE', 'api/admin/config/thresholds/{key}'));
        $this->assertNull($this->route('GET', 'api/auth/login'));
    }

    public function test_cau_hinh_geoserver_va_vietmap(): void
    {
        $gs = config('services.geoserver');
        $this->assertArrayHasKey('url', $gs);
        $this->assertArrayHasKey('workspace', $gs);
        $this->assertNotSame('', $gs['workspace']);
        $this->assertArrayHasKey('key', config('services.vietmap'));
    }

    public function test_hai_ket_noi_db_duoc_khai_bao(): void
    {
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('pgsql', config('database.connections.pgsql.driver'));
        $this->assertSame('webgis_app_test', config('database.connections.mysql.database'));
        $this->assertSame('webgis_spatial_test', config('database.connections.pgsql.database'));
    }
}
