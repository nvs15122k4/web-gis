<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class PageIconsTest extends TestCase
{
    public function test_cac_trang_ngoai_ban_do_co_icon_svg_cung_bo_voi_menu(): void
    {
        foreach (['/gioi-thieu', '/huong-dan', '/dang-nhap', '/khong-co-trang-nay'] as $path) {
            $this->get($path)->assertSee('<svg class="ico', false);
        }
        $this->get('/huong-dan')->assertSee('class="ico h-ico"', false);
        $this->get('/dang-nhap')->assertSee('Đăng nhập', false);
    }
}
