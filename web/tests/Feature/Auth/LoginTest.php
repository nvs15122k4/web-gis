<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public static function invalidPayloads(): array
    {
        return [
            'rỗng' => [[]],
            'thiếu mật khẩu' => [['email' => 'admin@webgis.test']],
            'thiếu email' => [['password' => 'password']],
            'email sai định dạng' => [['email' => 'khong-phai-email', 'password' => 'password']],
            'email là mảng' => [['email' => ['a@b.c'], 'password' => 'password']],
            'mật khẩu là mảng' => [['email' => 'admin@webgis.test', 'password' => ['x']]],
            'mật khẩu rỗng' => [['email' => 'admin@webgis.test', 'password' => '']],
            'email null' => [['email' => null, 'password' => 'password']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidPayloads')]
    public function test_invalid_payload_returns_422(array $payload): void
    {
        $this->postJson('/api/auth/login', $payload)->assertStatus(422)->assertJsonStructure(['message', 'errors']);
    }

    public static function wrongCredentials(): array
    {
        return [
            'sai mật khẩu' => ['admin@webgis.test', 'sai'],
            'email không tồn tại' => ['nobody@webgis.test', 'password'],
            'mật khẩu viết hoa' => ['admin@webgis.test', 'PASSWORD'],
            'SQL injection' => ["admin@webgis.test' OR '1'='1", 'x'],
            'mật khẩu có khoảng trắng' => ['admin@webgis.test', ' password '],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('wrongCredentials')]
    public function test_wrong_credentials_return_401_without_token(string $email, string $password): void
    {
        $r = $this->postJson('/api/auth/login', compact('email', 'password'));
        $this->assertContains($r->status(), [401, 422]);
        $this->assertArrayNotHasKey('token', $r->json());
    }

    public function test_wrong_password_message_is_generic(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'sai'])
            ->assertStatus(401)->assertJsonPath('message', 'Sai email hoặc mật khẩu.');
        $this->postJson('/api/auth/login', ['email' => 'nobody@webgis.test', 'password' => 'sai'])
            ->assertStatus(401)->assertJsonPath('message', 'Sai email hoặc mật khẩu.');
    }

    public static function accounts(): array
    {
        return [['admin@webgis.test', 'admin'], ['viewer@webgis.test', 'viewer']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accounts')]
    public function test_valid_login_returns_token_and_safe_user(string $email, string $role): void
    {
        $r = $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])->assertOk();
        $this->assertSame($role, $r->json('user.role'));
        $this->assertSame(['id', 'name', 'email', 'role'], array_keys($r->json('user')));
        $this->assertNotEmpty($r->json('token'));
        $this->assertStringNotContainsString('password', $r->getContent());
    }

    public function test_each_login_creates_a_new_token(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        $before = $admin->tokens()->count();
        $a = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'password'])->json('token');
        $b = $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'password'])->json('token');
        $this->assertNotSame($a, $b);
        $this->assertSame($before + 2, $admin->tokens()->count());
    }

    public function test_failed_login_creates_no_token(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        $this->postJson('/api/auth/login', ['email' => $admin->email, 'password' => 'sai']);
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_login_rejects_get(): void
    {
        $this->getJson('/api/auth/login')->assertStatus(405);
    }

    public function test_password_is_stored_hashed(): void
    {
        $this->assertNotSame('password', User::where('email', 'admin@webgis.test')->value('password'));
    }
}
