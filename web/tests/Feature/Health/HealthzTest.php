<?php

namespace Tests\Feature\Health;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

/** Không dùng Tests\TestCase: không cần dữ liệu mẫu, và ca lỗi phải đổi cấu hình kết nối. */
class HealthzTest extends TestCase
{
    public function test_get_healthz_tra_200_khi_csdl_song(): void
    {
        $this->getJson('/healthz')->assertOk()->assertExactJson(['status' => 'ok']);
    }

    public function test_head_healthz_tra_200(): void
    {
        $this->call('HEAD', '/healthz')->assertOk();
    }

    public function test_healthz_khong_tao_phien_hay_cookie(): void
    {
        $this->get('/healthz')->assertOk()->assertCookieMissing('webgis-session');
    }

    public function test_healthz_tra_503_khi_khong_ket_noi_duoc_csdl(): void
    {
        config(['database.connections.pgsql.host' => '127.0.0.1', 'database.connections.pgsql.port' => 1]);
        DB::purge('pgsql');

        $this->getJson('/healthz')->assertStatus(503)->assertExactJson(['status' => 'error']);
    }
}
