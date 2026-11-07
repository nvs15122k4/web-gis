<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    private const EMAIL = 'admin@webgis.test';

    private function attempt(string $password, string $email = self::EMAIL, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])
            ->postJson('/api/auth/login', ['email' => $email, 'password' => $password]);
    }

    private function failTimes(int $times, string $email = self::EMAIL, string $ip = '10.0.0.1'): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->attempt('sai', $email, $ip)->assertUnauthorized();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login:'.self::EMAIL.'|10.0.0.1');
    }

    public function test_constants_match_documented_policy(): void
    {
        $this->assertSame(5, AuthController::MAX_FAILED_ATTEMPTS);
        $this->assertSame(60, AuthController::LOCKOUT_SECONDS);
    }

    public function test_four_failures_do_not_lock(): void
    {
        $this->failTimes(4);
        $this->attempt('password')->assertOk();
    }

    public function test_locked_response_has_message_and_numeric_retry_after(): void
    {
        $this->failTimes(5);
        $r = $this->attempt('sai')->assertStatus(429);

        $this->assertStringContainsString('quá nhiều lần', $r->json('message'));
        $retry = (int) $r->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retry);
        $this->assertLessThanOrEqual(AuthController::LOCKOUT_SECONDS, $retry);
        $this->assertStringContainsString((string) $retry, $r->json('message'));
    }

    public function test_correct_password_is_refused_while_locked(): void
    {
        $this->failTimes(5);
        $this->attempt('password')->assertStatus(429)->assertJsonMissingPath('token');
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_attempts_while_locked_do_not_extend_the_lockout(): void
    {
        $this->failTimes(5);
        $this->travel(30)->seconds();
        $this->attempt('sai')->assertStatus(429);
        $this->attempt('sai')->assertStatus(429);

        $this->travel(31)->seconds();
        $this->attempt('password')->assertOk();
    }

    public function test_still_locked_just_before_expiry(): void
    {
        $this->failTimes(5);
        $this->travel(55)->seconds();
        $this->attempt('password')->assertStatus(429);
    }

    public function test_lockout_is_scoped_per_ip(): void
    {
        $this->failTimes(5);
        $this->attempt('password')->assertStatus(429);

        $this->attempt('password', self::EMAIL, '10.0.0.2')->assertOk();
    }

    public function test_lockout_ignores_email_case(): void
    {
        $this->failTimes(5, 'ADMIN@WEBGIS.TEST');
        $this->attempt('password', 'admin@webgis.test')->assertStatus(429);
    }

    public function test_unknown_email_is_throttled_too(): void
    {
        $this->failTimes(5, 'khongco@webgis.test');
        $this->attempt('sai', 'khongco@webgis.test')->assertStatus(429);
    }

    public function test_locking_one_email_does_not_lock_another_on_same_ip(): void
    {
        $this->failTimes(5);
        $this->attempt('password', 'viewer@webgis.test')->assertOk();
    }

    public function test_validation_errors_do_not_count_as_failures(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->postJson('/api/auth/login', ['email' => self::EMAIL])->assertStatus(422);
        }
        $this->attempt('password')->assertOk();
    }

    public function test_success_resets_counter_so_five_more_failures_are_allowed(): void
    {
        $this->failTimes(4);
        $this->attempt('password')->assertOk();
        $this->failTimes(5);
        $this->attempt('sai')->assertStatus(429);
    }

    public function test_successful_logins_are_never_throttled(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->attempt('password')->assertOk();
        }
        $this->assertSame(8, PersonalAccessToken::count());
    }
}
