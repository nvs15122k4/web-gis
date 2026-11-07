<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureIsAdmin;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;

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
        $request = Request::create('/x');
        (new EnsureIsAdmin)->handle($request, function () use (&$called) {
            $called = true;

            return response('');
        });
        $this->assertFalse($called);
    }

    public function test_alias_admin_da_dang_ky(): void
    {
        $this->assertSame(EnsureIsAdmin::class, $this->app['router']->getMiddleware()['admin']);
    }
}
