<?php

namespace Tests\Feature\Concurrency;

use App\Models\ActivityLog;
use App\Models\ThresholdConfig;
use App\Models\User;
use Tests\TestCase;

/** Mô phỏng ca đồng thời bằng các yêu cầu xen kẽ (một tiến trình PHP, không có khoá lạc quan). */
class ConcurrentUpdatesTest extends TestCase
{
    private const URL = '/api/admin/config/thresholds/min_erosion_rate';

    private function token(string $email): string
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/auth/login', ['email' => $email, 'password' => 'password'])->json('token');
    }

    private function putAs(string $token, $v)
    {
        $this->app['auth']->forgetGuards();

        return $this->flushHeaders()->withToken($token)->putJson(self::URL, ['value' => $v]);
    }

    public function test_last_write_wins_between_two_admins_and_both_are_logged(): void
    {
        $b = User::create(['name' => 'B', 'email' => 'b@webgis.test', 'password' => 'password', 'role' => 'admin']);
        $ta = $this->token('admin@webgis.test');
        $tb = $this->token('b@webgis.test');

        $this->putAs($ta, 2)->assertOk();
        $this->putAs($tb, 5)->assertOk();
        $this->putAs($ta, 3)->assertOk();

        $row = ThresholdConfig::where('key', 'min_erosion_rate')->first();
        $this->assertSame('3', $row->value);
        $this->assertSame(User::where('email', 'admin@webgis.test')->value('id'), (int) $row->updated_by);
        $this->assertSame(3, ActivityLog::count());
        $this->assertSame(1, ThresholdConfig::count());
    }

    public function test_no_optimistic_locking_stale_write_is_accepted(): void
    {
        $ta = $this->token('admin@webgis.test');
        $seen = $this->getJson('/api/config/thresholds')->json('0.value'); // client A đọc "1.0"
        $this->putAs($ta, 4)->assertOk();                                   // client B đổi
        $this->putAs($ta, 2)->assertOk();                                   // A ghi dựa trên số liệu cũ: vẫn thành công
        $this->assertSame('1.0', $seen);
        $this->assertSame('2', ThresholdConfig::first()->value);
    }

    public function test_many_rapid_updates_leave_exactly_one_row_and_n_logs(): void
    {
        $t = $this->token('admin@webgis.test');
        foreach (range(1, 25) as $i) {
            $this->putAs($t, $i)->assertOk();
        }
        $this->assertSame(1, ThresholdConfig::count());
        $this->assertSame(25, ActivityLog::count());
        $this->assertSame('25', ThresholdConfig::first()->value);
        $this->assertSame(25, ActivityLog::distinct()->count('action'));
    }

    public function test_reads_interleaved_with_writes_always_see_latest_committed_value(): void
    {
        $t = $this->token('admin@webgis.test');
        foreach ([2, 7, 0] as $v) {
            $this->putAs($t, $v)->assertOk();
            $this->assertSame((string) $v, $this->getJson('/api/config/thresholds')->json('0.value'));
        }
    }

    public function test_revoking_token_between_requests_blocks_the_next_one(): void
    {
        $t = $this->token('admin@webgis.test');
        $this->putAs($t, 2)->assertOk();
        \DB::table('personal_access_tokens')->delete();
        $this->putAs($t, 3)->assertUnauthorized();
        $this->assertSame('2', ThresholdConfig::first()->value);
    }

    public function test_two_tokens_for_same_user_are_independent(): void
    {
        $t1 = $this->token('admin@webgis.test');
        $t2 = $this->token('admin@webgis.test');
        $first = \DB::table('personal_access_tokens')->orderBy('id')->first();
        \DB::table('personal_access_tokens')->where('id', $first->id)->delete();
        $this->putAs($t1, 2)->assertUnauthorized();
        $this->putAs($t2, 2)->assertOk();
    }

    public function test_simultaneous_logins_create_distinct_tokens(): void
    {
        $tokens = [];
        foreach (range(1, 5) as $_) {
            $tokens[] = $this->token('admin@webgis.test');
        }
        $this->assertCount(5, array_unique($tokens));
        $this->assertSame(5, \DB::table('personal_access_tokens')->count());
    }

    public function test_demotion_between_requests_applies_immediately(): void
    {
        $t = $this->token('admin@webgis.test');
        $this->putAs($t, 2)->assertOk();
        User::where('email', 'admin@webgis.test')->update(['role' => 'viewer']);
        $this->putAs($t, 3)->assertForbidden();
        $this->assertSame('2', ThresholdConfig::first()->value);
        $this->assertSame(1, ActivityLog::count());
    }

    public function test_deleting_data_while_reading_returns_valid_empty_collections(): void
    {
        $pg = \DB::connection('pgsql');
        $this->assertCount(10, $this->getJson('/api/transects')->json('features'));
        $pg->table('transects')->delete();
        $this->assertSame([], $this->getJson('/api/transects')->json('features'));
        $this->assertSame(0, $this->getJson('/api/summary')->json('transects.total'));
        $this->assertSame("\xEF\xBB\xBF", $this->get('/api/export/transects.csv')->getContent());
    }

    public function test_row_lock_and_update_of_threshold_in_same_transaction_does_not_deadlock(): void
    {
        $t = $this->token('admin@webgis.test');
        \DB::table('threshold_configs')->where('key', 'min_erosion_rate')->lockForUpdate()->first();
        $this->putAs($t, 8)->assertOk();
        $this->assertSame('8', ThresholdConfig::first()->value);
    }

    public function test_fixture_reseed_mid_session_keeps_api_consistent(): void
    {
        $before = $this->getJson('/api/summary')->json();
        $this->seed(\Database\Seeders\E2eFixtureSeeder::class);
        $this->assertSame($before['transects']['total'], $this->getJson('/api/summary')->json('transects.total'));
        $this->assertSame($before['shorelines']['years'], $this->getJson('/api/summary')->json('shorelines.years'));
    }
}
