<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\ThresholdConfig;
use App\Models\User;
use Tests\TestCase;

class ThresholdUpdateTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('email', 'admin@webgis.test')->first();
    }

    private function putThreshold(string $key, array $body)
    {
        return $this->actingAs($this->admin, 'sanctum')->putJson("/api/admin/config/thresholds/{$key}", $body);
    }

    public static function invalidValues(): array
    {
        return [
            'thiếu' => [[]],
            'null' => [['value' => null]],
            'chuỗi chữ' => [['value' => 'abc']],
            'rỗng' => [['value' => '']],
            'mảng' => [['value' => [1]]],
            'boolean chữ' => [['value' => 'true']],
            'hex' => [['value' => '0x1A']],
            'số mũ lỗi' => [['value' => '1e']],
            'âm' => [['value' => -1]],
            'âm dạng chuỗi' => [['value' => '-0.5']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidValues')]
    public function test_invalid_values_return_422_and_do_not_change_or_log(array $body): void
    {
        $this->putThreshold('min_erosion_rate', $body)->assertStatus(422)->assertJsonValidationErrors('value');
        $this->assertSame('1.0', ThresholdConfig::where('key', 'min_erosion_rate')->value('value'));
        $this->assertSame(0, ActivityLog::count());
    }

    public static function validValues(): array
    {
        return [[2], [0], [0.5], ['3.25'], [100], ['7']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validValues')]
    public function test_valid_values_persist_and_log(int|float|string $v): void
    {
        $this->putThreshold('min_erosion_rate', ['value' => $v])->assertOk()->assertJsonStructure(['key', 'value', 'description']);
        $this->assertEquals((float) $v, (float) ThresholdConfig::where('key', 'min_erosion_rate')->value('value'));
        $log = ActivityLog::latest('id')->first();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame("update_threshold:min_erosion_rate={$v}", $log->action);
        $this->assertNotNull($log->ip_address);
        $this->assertNotNull($log->created_at);
    }

    public function test_updated_by_is_recorded(): void
    {
        $this->putThreshold('min_erosion_rate', ['value' => 2])->assertOk();
        $this->assertSame($this->admin->id, (int) ThresholdConfig::where('key', 'min_erosion_rate')->value('updated_by'));
    }

    public static function unknownKeys(): array
    {
        return [['khong_ton_tai'], ["x'; DROP TABLE users;--"], ['min_erosion_rat']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('unknownKeys')]
    public function test_unknown_key_404_no_log(string $key): void
    {
        $this->putThreshold($key, ['value' => 1])->assertNotFound();
        $this->assertSame(0, ActivityLog::count());
    }

    /** Ghi nhận hành vi hiện tại: collation MySQL không phân biệt hoa/thường nên khoá viết hoa vẫn khớp (xem báo cáo). */
    public function test_key_lookup_is_case_insensitive_due_to_mysql_collation(): void
    {
        $this->putThreshold('MIN_EROSION_RATE', ['value' => 2])->assertOk();
    }

    public function test_multiple_updates_create_one_log_each_in_order(): void
    {
        foreach ([1, 2, 3] as $v) {
            $this->putThreshold('min_erosion_rate', ['value' => $v])->assertOk();
        }
        $this->assertSame(
            ['update_threshold:min_erosion_rate=1', 'update_threshold:min_erosion_rate=2', 'update_threshold:min_erosion_rate=3'],
            ActivityLog::orderBy('id')->pluck('action')->all()
        );
    }

    public function test_update_is_visible_via_public_endpoint(): void
    {
        $this->putThreshold('min_erosion_rate', ['value' => 4.5])->assertOk();
        $this->getJson('/api/config/thresholds')->assertJsonFragment(['key' => 'min_erosion_rate', 'value' => '4.5']);
    }

    public function test_update_keeps_description(): void
    {
        $before = ThresholdConfig::where('key', 'min_erosion_rate')->value('description');
        $this->putThreshold('min_erosion_rate', ['value' => 2])->assertOk()->assertJsonPath('description', $before);
    }

    public function test_extra_fields_ignored(): void
    {
        $this->putThreshold('min_erosion_rate', ['value' => 2, 'description' => 'hack', 'key' => 'other'])->assertOk();
        $this->assertNotSame('hack', ThresholdConfig::where('key', 'min_erosion_rate')->value('description'));
        $this->assertSame(1, ThresholdConfig::where('key', 'min_erosion_rate')->count());
    }

    public function test_activity_log_has_no_updated_at(): void
    {
        $this->putThreshold('min_erosion_rate', ['value' => 2]);
        $this->assertArrayNotHasKey('updated_at', ActivityLog::first()->getAttributes());
    }

    public function test_forbidden_attempt_writes_no_log(): void
    {
        $v = User::where('email', 'viewer@webgis.test')->first();
        $this->actingAs($v, 'sanctum')->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 5])->assertForbidden();
        $this->assertSame(0, ActivityLog::count());
    }
}
