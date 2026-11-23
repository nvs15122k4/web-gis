<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Tests\TestCase;

class GuideAndReportAccessTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@webgis.test')->firstOrFail();
    }

    private function viewer(): User
    {
        return User::where('email', 'viewer@webgis.test')->firstOrFail();
    }

    public function test_khach_xem_duoc_huong_dan(): void
    {
        $this->get('/huong-dan')->assertOk()->assertSee('Hướng dẫn sử dụng');
    }

    public function test_huong_dan_co_trong_menu_cua_moi_nguoi(): void
    {
        $this->get('/huong-dan')->assertSee('href="/huong-dan"', false);
        $this->actingAs($this->viewer())->get('/huong-dan')->assertOk()->assertSee('href="/huong-dan"', false);
    }

    public function test_khach_vao_bao_cao_bi_chuyen_toi_dang_nhap(): void
    {
        $this->get('/bao-cao')->assertRedirect('/dang-nhap');
    }

    public function test_user_thuong_bi_403_va_khong_thay_noi_dung(): void
    {
        $this->actingAs($this->viewer())->get('/bao-cao')
            ->assertForbidden()
            ->assertDontSee('Tiến độ theo đề cương')
            ->assertSee('Không có quyền truy cập');
    }

    public function test_admin_xem_duoc_bao_cao(): void
    {
        $this->actingAs($this->admin())->get('/bao-cao')->assertOk()
            ->assertSee('Báo cáo công việc đã làm')
            ->assertSee('Tiến độ theo đề cương');
    }

    public function test_menu_bao_cao_chi_hien_voi_admin(): void
    {
        $this->get('/')->assertDontSee('href="/bao-cao"', false);
        $this->actingAs($this->viewer())->get('/')->assertDontSee('href="/bao-cao"', false);
        $this->actingAs($this->admin())->get('/')->assertSee('href="/bao-cao"', false);
    }

    public function test_token_sanctum_khong_thay_the_phien_dang_nhap_cho_trang_bao_cao(): void
    {
        $token = $this->admin()->createToken('t')->plainTextToken;
        $this->withHeader('Authorization', "Bearer {$token}")->get('/bao-cao')->assertRedirect('/dang-nhap');
    }

    public function test_dang_nhap_admin_roi_quay_lai_bao_cao(): void
    {
        $this->get('/bao-cao')->assertRedirect('/dang-nhap');
        $this->post('/dang-nhap', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertRedirect('/bao-cao');
        $this->get('/bao-cao')->assertOk();
    }

    public function test_dang_nhap_user_thuong_van_bi_403_o_bao_cao(): void
    {
        $this->post('/dang-nhap', ['email' => 'viewer@webgis.test', 'password' => 'password'])->assertRedirect('/');
        $this->get('/bao-cao')->assertForbidden();
    }

    public function test_dang_nhap_sai_mat_khau(): void
    {
        $this->from('/dang-nhap')->post('/dang-nhap', ['email' => 'admin@webgis.test', 'password' => 'sai'])
            ->assertRedirect('/dang-nhap')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_dang_xuat_xoa_phien(): void
    {
        $this->actingAs($this->admin())->post('/dang-xuat')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/bao-cao')->assertRedirect('/dang-nhap');
    }

    public function test_dang_xuat_can_dang_nhap(): void
    {
        $this->post('/dang-xuat')->assertRedirect('/dang-nhap');
    }

    public function test_api_admin_van_tra_json_401_403(): void
    {
        $this->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 1])->assertUnauthorized();
        $this->actingAs($this->viewer(), 'sanctum')->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 1])
            ->assertForbidden()->assertJson(['message' => 'Forbidden']);
    }
}
