<?php

namespace Tests\Feature\Auth;

use App\Models\ActivityLog;
use App\Models\ThresholdConfig;
use App\Models\User;
use Tests\TestCase;

class RbacTest extends TestCase
{
    private const URL = '/api/admin/config/thresholds/min_erosion_rate';

    private function bearer(string $email): array
    {
        $token = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])->json('token');

        return ['Authorization' => "Bearer {$token}"];
    }

    public function test_guest_gets_401(): void
    {
        $this->putJson(self::URL, ['value' => 2])->assertUnauthorized();
    }

    public function test_invalid_bearer_token_gets_401(): void
    {
        $this->putJson(self::URL, ['value' => 2], ['Authorization' => 'Bearer 999|khongdung'])->assertUnauthorized();
    }

    public static function nonAdminRoles(): array
    {
        return [['viewer'], ['editor'], [''], ['ADMIN'], ['administrator']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('nonAdminRoles')]
    public function test_non_admin_roles_get_403(string $role): void
    {
        $u = User::create(['name' => 'U', 'email' => 'u'.uniqid().'@webgis.test', 'password' => 'password', 'role' => $role]);
        $this->actingAs($u, 'sanctum')->putJson(self::URL, ['value' => 9])->assertForbidden()->assertJsonPath('message', 'Forbidden');
        $this->assertSame('1.0', ThresholdConfig::where('key', 'min_erosion_rate')->value('value'));
    }

    public function test_viewer_token_via_real_login_is_forbidden(): void
    {
        $this->putJson(self::URL, ['value' => 9], $this->bearer('viewer@webgis.test'))->assertForbidden();
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_admin_token_via_real_login_can_update(): void
    {
        $this->putJson(self::URL, ['value' => 3], $this->bearer('admin@webgis.test'))->assertOk()->assertJsonPath('value', '3');
    }

    public function test_revoked_token_loses_access(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        $headers = $this->bearer($admin->email);
        $this->putJson(self::URL, ['value' => 2], $headers)->assertOk();
        $admin->tokens()->delete();
        $this->app['auth']->forgetGuards();
        $this->putJson(self::URL, ['value' => 3], $headers)->assertUnauthorized();
    }

    public function test_demoted_admin_is_forbidden_immediately(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        $headers = $this->bearer($admin->email);
        $admin->update(['role' => 'viewer']);
        $this->app['auth']->forgetGuards();
        $this->putJson(self::URL, ['value' => 3], $headers)->assertForbidden();
    }

    public function test_is_admin_helper(): void
    {
        $this->assertTrue(User::where('email', 'admin@webgis.test')->first()->isAdmin());
        $this->assertFalse(User::where('email', 'viewer@webgis.test')->first()->isAdmin());
        $this->assertFalse((new User)->isAdmin());
    }

    public function test_public_read_stays_open_after_admin_login(): void
    {
        $this->getJson('/api/config/thresholds', $this->bearer('viewer@webgis.test'))->assertOk();
    }

    public function test_no_admin_route_for_other_verbs(): void
    {
        $h = $this->bearer('admin@webgis.test');
        $this->getJson(self::URL, $h)->assertStatus(405);
        $this->deleteJson(self::URL, [], $h)->assertStatus(405);
    }
}
