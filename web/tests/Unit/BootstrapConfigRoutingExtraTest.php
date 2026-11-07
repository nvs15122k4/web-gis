<?php

namespace Tests\Unit;

use App\Http\Controllers\MapController;
use App\Http\Middleware\EnsureIsAdmin;
use App\Models\Shoreline;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BootstrapConfigRoutingExtraTest extends PretendQueryCase
{
    private function match(string $method, string $uri): LRoute
    {
        return Route::getRoutes()->match(Request::create($uri, $method));
    }

    // ---- routing ----

    public static function matchingUris(): array
    {
        return [
            ['GET', '/api/status'], ['GET', '/api/summary'], ['GET', '/api/shorelines'], ['GET', '/api/transects'],
            ['GET', '/api/transects/12'], ['GET', '/api/transects/12/history'], ['GET', '/api/erosion-hotspots'],
            ['GET', '/api/reference-events'], ['GET', '/api/config/thresholds'], ['POST', '/api/auth/login'],
            ['PUT', '/api/admin/config/thresholds/min_rate'],
            ['GET', '/api/export/transects.csv'], ['GET', '/api/export/shorelines.geojson'],
            ['GET', '/api/export/hotspots.csv'], ['GET', '/api/export/reference.geojson'],
            ['GET', '/'], ['GET', '/gioi-thieu'], ['GET', '/admin'], ['GET', '/up'],
        ];
    }

    #[DataProvider('matchingUris')]
    public function test_uri_hop_le_khop_route(string $method, string $uri): void
    {
        $this->assertInstanceOf(LRoute::class, $this->match($method, $uri));
    }

    public static function nonMatchingUris(): array
    {
        return [
            'layer la' => ['GET', '/api/export/users.csv'], 'format la' => ['GET', '/api/export/transects.xml'],
            'thieu format' => ['GET', '/api/export/transects'], 'id chu' => ['GET', '/api/transects/abc'],
            'id am' => ['GET', '/api/transects/-1'], 'id thap phan' => ['GET', '/api/transects/1.5'],
            'history id chu' => ['GET', '/api/transects/x/history'], 'history thieu id' => ['GET', '/api/transects//history'],
            'route khong ton tai' => ['GET', '/api/khong-co'], 'trang khong ton tai' => ['GET', '/khong-co'],
            'POST vao status' => ['POST', '/api/status'], 'PUT vao login' => ['PUT', '/api/auth/login'],
            'GET admin threshold' => ['GET', '/api/admin/config/thresholds/x'],
        ];
    }

    #[DataProvider('nonMatchingUris')]
    public function test_uri_khong_hop_le_khong_khop(string $method, string $uri): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->match($method, $uri);
    }

    public function test_route_admin_co_dung_hai_middleware_theo_thu_tu(): void
    {
        $mw = $this->match('PUT', '/api/admin/config/thresholds/x')->gatherMiddleware();
        $this->assertSame(['auth:sanctum', 'admin'], array_values(array_filter($mw, fn ($m) => is_string($m) && in_array($m, ['auth:sanctum', 'admin']))));
    }

    public static function publicRouteUris(): array
    {
        return [['/api/status'], ['/api/summary'], ['/api/shorelines'], ['/api/transects'], ['/api/export/transects.csv'], ['/api/config/thresholds'], ['/api/reference-events'], ['/api/erosion-hotspots']];
    }

    #[DataProvider('publicRouteUris')]
    public function test_route_doc_khong_yeu_cau_admin(string $uri): void
    {
        $this->assertNotContains('admin', $this->match('GET', $uri)->gatherMiddleware());
    }

    public function test_tat_ca_route_api_co_tien_to_api(): void
    {
        foreach (Route::getRoutes() as $r) {
            if (str_contains($r->getActionName(), 'Controllers\\Api\\')) {
                $this->assertStringStartsWith('api/', $r->uri());
            }
        }
    }

    public function test_moi_route_controller_tro_toi_phuong_thuc_ton_tai(): void
    {
        foreach (Route::getRoutes() as $r) {
            $a = $r->getAction('uses');
            if (is_string($a) && str_contains($a, '@')) {
                [$c, $m] = explode('@', $a);
                $this->assertTrue(method_exists($c, $m), $a);
            }
        }
    }

    // ---- bootstrap/app.php: xử lý exception ----

    private function render(\Throwable $e, string $uri, array $headers = []): \Symfony\Component\HttpFoundation\Response
    {
        $req = Request::create($uri, 'GET', server: $headers);
        $this->app->instance('request', $req);

        return $this->app->make(ExceptionHandler::class)->render($req, $e);
    }

    private function queryException(): QueryException
    {
        return new QueryException('pgsql', 'select 1', [], new \PDOException('connection refused'));
    }

    public function test_query_exception_tren_api_tra_503_dang_cap_nhat(): void
    {
        $res = $this->render($this->queryException(), '/api/summary');
        $this->assertSame(503, $res->getStatusCode());
        $this->assertSame(['message' => 'Dữ liệu đang cập nhật.', 'status' => 'updating'], json_decode($res->getContent(), true));
    }

    public function test_pdo_exception_tren_api_cung_tra_503(): void
    {
        $this->assertSame(503, $this->render(new \PDOException('x'), '/api/status')->getStatusCode());
    }

    public function test_query_exception_ngoai_api_khong_bi_chuyen_thanh_503(): void
    {
        $res = $this->render($this->queryException(), '/gioi-thieu');
        $this->assertNotSame(503, $res->getStatusCode());
        $this->assertSame(500, $res->getStatusCode());
    }

    public function test_loi_khac_tren_api_van_la_json(): void
    {
        $res = $this->render(new NotFoundHttpException('x'), '/api/khong-co');
        $this->assertSame(404, $res->getStatusCode());
        $this->assertStringContainsString('json', $res->headers->get('Content-Type'));
    }

    public function test_request_expects_json_ngoai_api_cung_nhan_json(): void
    {
        $res = $this->render(new NotFoundHttpException('x'), '/khong-co', ['HTTP_ACCEPT' => 'application/json']);
        $this->assertStringContainsString('json', $res->headers->get('Content-Type'));
    }

    public function test_loi_web_thuong_khong_ep_json(): void
    {
        $res = $this->render(new NotFoundHttpException('x'), '/khong-co', ['HTTP_ACCEPT' => 'text/html']);
        $this->assertStringNotContainsString('json', (string) $res->headers->get('Content-Type'));
    }

    // ---- middleware / provider ----

    public function test_alias_admin_tro_toi_ensure_is_admin(): void
    {
        $this->assertSame(EnsureIsAdmin::class, $this->app['router']->getMiddleware()['admin']);
    }

    public function test_provider_nap_thu_muc_migration_pgsql(): void
    {
        $this->assertContains(database_path('migrations/pgsql'), $this->app['migrator']->paths());
        $this->assertDirectoryExists(database_path('migrations/pgsql'));
    }

    public function test_provider_da_dang_ky(): void
    {
        $this->assertArrayHasKey(AppServiceProvider::class, $this->app->getLoadedProviders());
    }

    public function test_cac_file_migration_pgsql_khai_bao_ket_noi_pgsql(): void
    {
        $files = glob(database_path('migrations/pgsql/*.php'));
        $this->assertNotEmpty($files);
        foreach ($files as $f) {
            $this->assertMatchesRegularExpression('/\$connection\s*=\s*[\'"]pgsql[\'"]/', file_get_contents($f), basename($f));
        }
    }

    // ---- config ----

    public function test_cau_hinh_app(): void
    {
        $this->assertSame('testing', config('app.env'));
        $this->assertNotEmpty(config('app.key'));
        $this->assertNotEmpty(config('app.timezone'));
        $this->assertNotEmpty(config('app.locale'));
    }

    public function test_guard_va_provider_xac_thuc(): void
    {
        $this->assertArrayHasKey('web', config('auth.guards'));
        $this->assertSame(\App\Models\User::class, config('auth.providers.users.model'));
    }

    public function test_sanctum_guard_va_stateful(): void
    {
        $this->assertIsArray(config('sanctum.guard'));
        $this->assertArrayHasKey('stateful', config('sanctum'));
    }

    public function test_pgsql_connection_chi_tiet(): void
    {
        $c = config('database.connections.pgsql');
        foreach (['host', 'port', 'database', 'username', 'search_path'] as $k) {
            $this->assertArrayHasKey($k, $c, $k);
        }
        $this->assertSame('mysql', config('database.connections.mysql.driver'));
    }

    public function test_geoserver_url_khong_ket_thuc_bang_gach_cheo_hoac_ghi_ro(): void
    {
        $url = config('services.geoserver.url');
        $this->assertMatchesRegularExpression('#^https?://#', $url);
        $this->assertDoesNotMatchRegularExpression('#/$#', $url, 'URL kết thúc bằng "/" sẽ tạo "//workspace/wms"');
    }

    public function test_workspace_geoserver_khong_chua_ky_tu_la(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_\-]+$/', config('services.geoserver.workspace'));
    }

    // ---- MapController ----

    public function test_available_years_rong_khi_khong_co_dong(): void
    {
        $m = (new \ReflectionClass(MapController::class))->getMethod('availableYears');
        $r = $this->capture(fn () => $m->invoke(new MapController));
        $this->assertSame([], $r['result']);
        $this->assertStringContainsString('select distinct "year" from "shorelines" order by "year" asc', $r['queries'][0]['sql']);
    }

    public function test_available_years_nuot_loi_ket_noi_va_tra_mang_rong(): void
    {
        $orig = Shoreline::getConnectionResolver();
        $bad = new class implements \Illuminate\Database\ConnectionResolverInterface
        {
            public function connection($name = null)
            {
                throw new \RuntimeException('no db');
            }

            public function getDefaultConnection()
            {
                return 'pgsql';
            }

            public function setDefaultConnection($name) {}
        };
        Shoreline::setConnectionResolver($bad);
        try {
            $m = (new \ReflectionClass(MapController::class))->getMethod('availableYears');
            $this->assertSame([], $m->invoke(new MapController));
        } finally {
            Shoreline::setConnectionResolver($orig);
        }
    }

    public function test_about_va_admin_view_ton_tai(): void
    {
        $this->assertTrue(view()->exists('about.index'));
        $this->assertTrue(view()->exists('admin.index'));
        $this->assertTrue(view()->exists('map.index'));
    }

    public function test_map_nam_nghien_cuu_lien_tuc_2018_2026(): void
    {
        $years = (new MapController)->about()->getData()['years'];
        $this->assertSame(range(2018, 2026), $years);
        $this->assertCount(9, $years);
    }
}
