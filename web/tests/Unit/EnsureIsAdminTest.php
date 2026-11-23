<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureIsAdminTest extends BaseUnitTest
{
    private function runMw(?User $user): Response
    {
        $request = Request::create('/api/admin/x', 'PUT');
        $request->setUserResolver(fn () => $user);

        return (new EnsureIsAdmin)->handle($request, fn () => response('ok', 200));
    }

    public function test_admin_di_qua(): void
    {
        $res = $this->runMw(new User(['role' => 'admin']));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame('ok', $res->getContent());
    }

    public function test_khach_chua_dang_nhap_bi_403(): void
    {
        $res = $this->runMw(null);
        $this->assertSame(403, $res->getStatusCode());
        $this->assertSame(['message' => 'Forbidden'], json_decode($res->getContent(), true));
    }

    public static function nonAdminRoles(): array
    {
        return [['viewer'], ['user'], ['Admin'], [''], [null]];
    }

    #[DataProvider('nonAdminRoles')]
    public function test_user_khong_phai_admin_bi_403(?string $role): void
    {
        $this->assertSame(403, $this->runMw(new User(['role' => $role]))->getStatusCode());
    }

    public function test_khong_goi_next_khi_bi_chan(): void
    {
        $called = false;
        $request = Request::create('/api/x');
        (new EnsureIsAdmin)->handle($request, function () use (&$called) {
            $called = true;

            return response('');
        });
        $this->assertFalse($called);
    }

    public function test_trang_web_khong_phai_admin_bi_abort_403(): void
    {
        $request = Request::create('/bao-cao');
        $request->setUserResolver(fn () => new User(['role' => 'viewer']));
        try {
            (new EnsureIsAdmin)->handle($request, fn () => response('ok'));
            $this->fail('Phải ném HttpException 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_yeu_cau_json_o_trang_web_van_nhan_json_403(): void
    {
        $request = Request::create('/bao-cao', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $request->setUserResolver(fn () => null);
        $res = (new EnsureIsAdmin)->handle($request, fn () => response('ok'));
        $this->assertSame(403, $res->getStatusCode());
    }

    public function test_alias_admin_da_dang_ky(): void
    {
        $this->assertSame(EnsureIsAdmin::class, $this->app['router']->getMiddleware()['admin']);
    }
}
