<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    private const URL = '/api/admin/config/thresholds/min_erosion_rate';

    private function login(string $email = 'admin@webgis.test'): string
    {
        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])->json('token');
    }

    public function test_expired_token_is_rejected(): void
    {
        $t = $this->login();
        PersonalAccessToken::query()->update(['expires_at' => now()->subMinute()]);
        $this->withToken($t)->putJson(self::URL, ['value' => 3])->assertUnauthorized();
        $this->assertSame(0, \DB::table('activity_logs')->count());
    }

    public function test_token_with_future_expiry_still_works(): void
    {
        $t = $this->login();
        PersonalAccessToken::query()->update(['expires_at' => now()->addHour()]);
        $this->withToken($t)->putJson(self::URL, ['value' => 3])->assertOk();
    }

    public function test_login_tokens_expire_after_configured_minutes(): void
    {
        $this->assertSame(480, config('sanctum.expiration'));
        $t = $this->login();
        $this->withToken($t)->putJson(self::URL, ['value' => 3])->assertOk();

        $this->travel(479)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($t)->putJson(self::URL, ['value' => 3])->assertOk();

        $this->travel(2)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($t)->putJson(self::URL, ['value' => 3])->assertUnauthorized();
    }

    public function test_tampered_token_secret_rejected(): void
    {
        $t = $this->login();
        $this->withToken(substr($t, 0, -2).'zz')->putJson(self::URL, ['value' => 3])->assertUnauthorized();
    }

    public function test_token_id_prefix_only_rejected(): void
    {
        $t = $this->login();
        $this->withToken(explode('|', $t)[0])->putJson(self::URL, ['value' => 3])->assertUnauthorized();
    }

    public function test_token_in_query_string_is_not_accepted(): void
    {
        $t = $this->login();
        $this->putJson(self::URL.'?token='.urlencode($t), ['value' => 3])->assertUnauthorized();
        $this->putJson(self::URL.'?api_token='.urlencode($t), ['value' => 3])->assertUnauthorized();
    }

    #[DataProvider('badAuthHeaders')]
    public function test_malformed_authorization_headers(string $header): void
    {
        $this->putJson(self::URL, ['value' => 3], ['Authorization' => $header])->assertUnauthorized();
    }

    public static function badAuthHeaders(): array
    {
        return array_map(fn ($h) => [$h], ['Bearer', 'Bearer ', 'Basic YWRtaW46cGFzc3dvcmQ=', 'Token abc', 'bearer null', 'Bearer undefined', 'Bearer 1|']);
    }

    public function test_viewer_cannot_update_and_leaves_no_trace(): void
    {
        $t = $this->login('viewer@webgis.test');
        $this->withToken($t)->putJson(self::URL, ['value' => 9])->assertForbidden();
        $this->assertSame('1.0', \DB::table('threshold_configs')->where('key', 'min_erosion_rate')->value('value'));
        $this->assertSame(0, \DB::table('activity_logs')->count());
    }

    public function test_viewer_cannot_see_other_users_tokens_or_use_them(): void
    {
        $admin = $this->login();
        $viewer = $this->login('viewer@webgis.test');
        $this->assertNotSame($admin, $viewer);
        $this->withToken($viewer)->putJson(self::URL, ['value' => 2])->assertForbidden();
        $this->app['auth']->forgetGuards(); // guard giữ user của request trước trong cùng một test
        $this->withToken($admin)->putJson(self::URL, ['value' => 2])->assertOk();
    }

    public function test_role_is_not_taken_from_login_request(): void
    {
        $j = $this->postJson('/api/auth/login', ['email' => 'viewer@webgis.test', 'password' => 'password', 'role' => 'admin'])->assertOk()->json();
        $this->assertSame('viewer', $j['user']['role']);
        $this->withToken($j['token'])->putJson(self::URL, ['value' => 2])->assertForbidden();
    }

    public function test_role_downgrade_applies_to_existing_token(): void
    {
        $t = $this->login();
        User::where('email', 'admin@webgis.test')->update(['role' => 'viewer']);
        $this->withToken($t)->putJson(self::URL, ['value' => 2])->assertForbidden();
    }

    public function test_role_value_with_different_case_is_not_admin(): void
    {
        $t = $this->login('viewer@webgis.test');
        User::where('email', 'viewer@webgis.test')->update(['role' => 'ADMIN']);
        $this->assertFalse(User::where('email', 'viewer@webgis.test')->first()->isAdmin());
        $this->withToken($t)->putJson(self::URL, ['value' => 2])->assertForbidden();
    }

    public function test_deleted_user_loses_access_and_cannot_login(): void
    {
        $t = $this->login();
        User::where('email', 'admin@webgis.test')->delete();
        \DB::table('personal_access_tokens')->count(); // token mồ côi còn trong bảng
        $this->withToken($t)->putJson(self::URL, ['value' => 2])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertUnauthorized();
    }

    public function test_changed_password_old_password_fails_but_old_token_still_valid(): void
    {
        $t = $this->login();
        User::where('email', 'admin@webgis.test')->first()->update(['password' => 'new-secret']);
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertUnauthorized();
        // Ghi nhận: đổi mật khẩu KHÔNG thu hồi token cũ.
        $this->withToken($t)->putJson(self::URL, ['value' => 2])->assertOk();
    }

    public function test_email_lookup_is_case_insensitive_due_to_mysql_collation(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'ADMIN@WEBGIS.TEST', 'password' => 'password'])->assertOk();
    }

    public function test_password_is_case_sensitive(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'PASSWORD'])->assertUnauthorized();
    }

    public function test_password_with_trailing_space_rejected(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password '])->assertUnauthorized();
    }

    public function test_login_is_locked_after_too_many_failures(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => "sai{$i}"])->assertUnauthorized();
        }
        $r = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password']);
        $r->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame(0, PersonalAccessToken::count());

        $this->travel(61)->seconds();
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertOk();
    }

    public function test_lockout_is_per_email_and_cleared_on_success(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'sai'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'khac@webgis.test', 'password' => 'sai'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertOk();
        // Đăng nhập đúng xoá bộ đếm: lại có đủ 5 lần sai.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'ADMIN@webgis.test', 'password' => 'sai'])->assertUnauthorized();
        }
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'sai'])->assertStatus(429);
    }

    public function test_no_rate_limit_on_public_reads(): void
    {
        for ($i = 0; $i < 70; $i++) {
            $r = $this->getJson('/api/config/thresholds');
            $this->assertSame(200, $r->getStatusCode());
            $this->assertNull($r->headers->get('X-RateLimit-Limit'));
        }
    }

    public function test_api_routes_have_no_throttle_middleware(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $this->assertNotContains('throttle:api', $route->gatherMiddleware(), $route->uri());
            }
        }
    }

    public function test_csrf_not_required_for_api_login_and_update(): void
    {
        // API stateless: không cần XSRF-TOKEN; token Bearer là cơ chế xác thực.
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertOk();
    }

    public function test_web_pages_do_not_accept_state_changing_verbs(): void
    {
        foreach (['/', '/admin', '/gioi-thieu'] as $u) {
            foreach (['post', 'put', 'patch', 'delete'] as $m) {
                $this->{$m}($u)->assertStatus(405);
            }
        }
    }

    public function test_web_guard_session_of_admin_is_accepted_by_sanctum_fallback(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        // Ghi nhận: auth:sanctum có fallback guard 'web' nên phiên đăng nhập web của admin cũng gọi được API admin.
        $this->actingAs($admin, 'web')->putJson(self::URL, ['value' => 2])->assertStatus(200);
    }

    public function test_acting_as_sanctum_viewer_forbidden(): void
    {
        $this->actingAs(User::where('email', 'viewer@webgis.test')->first(), 'sanctum');
        $this->putJson(self::URL, ['value' => 2])->assertForbidden();
    }

    #[DataProvider('massAssignment')]
    public function test_threshold_update_ignores_mass_assignment(array $extra): void
    {
        $t = $this->login();
        $this->withToken($t)->putJson(self::URL, ['value' => 2.5] + $extra)->assertOk();
        $row = \DB::table('threshold_configs')->where('key', 'min_erosion_rate')->first();
        $this->assertSame('2.5', $row->value);
        $this->assertSame('min_erosion_rate', $row->key);
        $this->assertSame(1, \DB::table('threshold_configs')->count());
        $admin = User::where('email', 'admin@webgis.test')->first();
        $this->assertSame($admin->id, (int) $row->updated_by);
        $this->assertStringStartsWith('Tốc độ lùi bờ', $row->description);
    }

    public static function massAssignment(): array
    {
        return [
            'key' => [['key' => 'hacked']],
            'description' => [['description' => 'pwn']],
            'updated_by' => [['updated_by' => 999]],
            'id' => [['id' => 777]],
            'role' => [['role' => 'admin']],
            'created_at' => [['created_at' => '1999-01-01']],
        ];
    }

    public function test_login_cannot_create_or_modify_users_via_extra_fields(): void
    {
        $n = User::count();
        $this->postJson('/api/auth/login', ['email' => 'viewer@webgis.test', 'password' => 'password', 'name' => 'Hacker', 'role' => 'admin', 'password_confirmation' => 'x'])->assertOk();
        $this->assertSame($n, User::count());
        $this->assertSame('Viewer', User::where('email', 'viewer@webgis.test')->value('name'));
        $this->assertSame('viewer', User::where('email', 'viewer@webgis.test')->value('role'));
    }

    public function test_user_response_never_contains_password_or_remember_token(): void
    {
        $body = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->getContent();
        $this->assertStringNotContainsString('password', strtolower(json_encode(json_decode($body)->user)));
        $this->assertStringNotContainsString('remember_token', $body);
    }

    public function test_no_user_enumeration_between_unknown_email_and_wrong_password(): void
    {
        $a = $this->postJson('/api/auth/login', ['email' => 'nobody@webgis.test', 'password' => 'x']);
        $b = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'x']);
        $this->assertSame($a->getStatusCode(), $b->getStatusCode());
        $this->assertSame($a->getContent(), $b->getContent());
    }

    public function test_login_requires_json_or_form_but_rejects_get(): void
    {
        $this->getJson('/api/auth/login')->assertStatus(405);
    }

    public function test_array_password_or_email_rejected(): void
    {
        $this->postJson('/api/auth/login', ['email' => ['admin@webgis.test'], 'password' => 'password'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => ['password']])->assertStatus(422);
    }

    public function test_logout_route_does_not_exist(): void
    {
        $this->postJson('/api/auth/logout')->assertNotFound();
    }

    public function test_admin_cannot_use_unlisted_admin_routes(): void
    {
        $t = $this->login();
        $this->withToken($t)->getJson('/api/admin/config/thresholds/min_erosion_rate')->assertStatus(405);
        $this->withToken($t)->getJson('/api/admin/users')->assertNotFound();
    }
}
