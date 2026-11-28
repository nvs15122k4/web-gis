<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

/** Trang Quy trình QGIS tách riêng khỏi trang Phương pháp. */
class WorkflowPageTest extends TestCase
{
    public function test_quy_trinh_qgis_co_chin_buoc_va_nam_trong_menu(): void
    {
        $r = $this->get('/quy-trinh-qgis')->assertOk()
            ->assertSee('Quy trình giải đoán ảnh bằng QGIS')
            ->assertSee('Từ ảnh vệ tinh đến đường bờ và tốc độ biến động')
            ->assertSee('href="/quy-trinh-qgis"', false);
        $this->assertSame(9, substr_count($r->getContent(), 'class="step"'));
    }

    public function test_phuong_phap_khong_con_chin_buoc_nhung_tro_sang_trang_moi(): void
    {
        $r = $this->get('/gioi-thieu')->assertOk()->assertSee('href="/quy-trinh-qgis"', false);
        $this->assertSame(0, substr_count($r->getContent(), 'class="step"'));
    }
}
