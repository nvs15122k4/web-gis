<?php

namespace Tests\Feature\Regression;

use App\Models\ActivityLog;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActivityLogBehaviourTest extends TestCase
{
    private const URL = '/api/admin/config/thresholds/min_erosion_rate';

    private function login(string $e = 'admin@webgis.test'): string
    {
        return $this->postJson('/api/auth/login', ['email' => $e, 'password' => 'password'])->json('token');
    }

    private function update($v, ?string $token = null)
    {
        return $this->withToken($token ?? $this->login())->putJson(self::URL, ['value' => $v]);
    }

    public function test_log_row_fields(): void
    {
        $this->update(2)->assertOk();
        $log = ActivityLog::first();
        $this->assertSame(User::where('email', 'admin@webgis.test')->value('id'), (int) $log->user_id);
        $this->assertSame('update_threshold:min_erosion_rate=2', $log->action);
        $this->assertNotEmpty($log->ip_address);
        $this->assertNotNull($log->created_at);
    }

    public function test_log_ip_is_loopback_in_tests_and_not_spoofable_via_xff_by_default(): void
    {
        $this->withHeader('X-Forwarded-For', '6.6.6.6')->withToken($this->login())->putJson(self::URL, ['value' => 2])->assertOk();
        $this->assertSame('127.0.0.1', ActivityLog::first()->ip_address);
    }

    #[DataProvider('values')]
    public function test_action_embeds_submitted_value_verbatim(mixed $input, string $expected): void
    {
        $this->update($input)->assertOk();
        $this->assertSame("update_threshold:min_erosion_rate={$expected}", ActivityLog::first()->action);
    }

    public static function values(): array
    {
        return [
            'int' => [3, '3'],
            'float' => [1.25, '1.25'],
            'string number' => ['2.50', '2.50'],
            'zero' => [0, '0'],
            'exp' => ['1e2', '1e2'],
            'leading space is trimmed by middleware' => [' 4', '4'],
        ];
    }

    public function test_one_log_per_successful_update_only(): void
    {
        $t = $this->login();
        $this->withToken($t)->putJson(self::URL, ['value' => 1])->assertOk();
        $this->withToken($t)->putJson(self::URL, ['value' => 'abc'])->assertStatus(422);
        $this->withToken($t)->putJson(self::URL, ['value' => -1])->assertStatus(422);
        $this->withToken($t)->putJson('/api/admin/config/thresholds/nope', ['value' => 1])->assertNotFound();
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_login_is_not_logged(): void
    {
        $this->login();
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'bad']);
        $this->assertSame(0, ActivityLog::count()); // ghi nhận: không audit đăng nhập
    }

    public function test_reads_are_not_logged(): void
    {
        foreach (['/api/summary', '/api/transects', '/api/config/thresholds', '/api/export/transects.csv'] as $u) {
            $this->get($u);
        }
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_denied_attempts_are_not_logged(): void
    {
        $this->update(2, $this->login('viewer@webgis.test'))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->putJson(self::URL, ['value' => 2])->assertUnauthorized();
        $this->assertSame(0, ActivityLog::count()); // ghi nhận: không ghi nhận nỗ lực truy cập trái phép
    }

    public function test_logs_keep_chronological_order_and_ids_increase(): void
    {
        $t = $this->login();
        foreach ([1, 2, 3] as $v) {
            $this->withToken($t)->putJson(self::URL, ['value' => $v])->assertOk();
        }
        $actions = ActivityLog::orderBy('id')->pluck('action')->all();
        $this->assertSame(['update_threshold:min_erosion_rate=1', 'update_threshold:min_erosion_rate=2', 'update_threshold:min_erosion_rate=3'], $actions);
    }

    public function test_same_value_update_still_logs(): void
    {
        $t = $this->login();
        $this->withToken($t)->putJson(self::URL, ['value' => 1])->assertOk();
        $this->withToken($t)->putJson(self::URL, ['value' => 1])->assertOk();
        $this->assertSame(2, ActivityLog::count());
    }

    public function test_log_is_attributed_to_the_acting_admin_when_two_admins_exist(): void
    {
        $second = User::create(['name' => 'Admin 2', 'email' => 'admin2@webgis.test', 'password' => 'password', 'role' => 'admin']);
        $this->update(2, $this->login());
        $this->app['auth']->forgetGuards();
        $this->update(3, $this->login('admin2@webgis.test'));
        $this->assertSame([User::where('email', 'admin@webgis.test')->value('id'), $second->id], ActivityLog::orderBy('id')->pluck('user_id')->map(fn ($i) => (int) $i)->all());
    }

    public function test_activity_log_model_mass_assignment_is_limited(): void
    {
        $l = ActivityLog::create(['user_id' => null, 'action' => 'x', 'ip_address' => '1.1.1.1', 'id' => 5000, 'created_at' => '1999-01-01 00:00:00']);
        $this->assertNotSame(5000, $l->id);
        $this->assertNotSame('1999', $l->fresh()->created_at->format('Y'));
    }

    public function test_activity_log_has_expected_columns(): void
    {
        $this->assertSame(['id', 'user_id', 'action', 'ip_address', 'created_at'], \Schema::getColumnListing('activity_logs'));
    }

    public function test_activity_log_not_exposed_by_any_api_route(): void
    {
        $this->update(2)->assertOk();
        foreach (['/api/activity-logs', '/api/admin/activity-logs', '/api/admin/logs'] as $u) {
            $this->withToken($this->login())->getJson($u)->assertNotFound();
        }
    }

    public function test_action_length_limit_not_exceeded_by_long_value(): void
    {
        // Giá trị lớn nhất cho phép (1e9) vẫn ghi được vào action VARCHAR(255).
        $this->update('1000000000')->assertOk();
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_overlong_numeric_value_returns_422(): void
    {
        // Đã sửa: value vượt max:1000000000 bị từ chối 422, không còn tràn cột (trước đây trả 503).
        $v = str_repeat('9', 300);
        $this->update($v)->assertStatus(422)->assertJsonValidationErrors('value');
        $this->update('1000000001')->assertStatus(422);
        $this->assertSame(0, ActivityLog::count());
    }
}
