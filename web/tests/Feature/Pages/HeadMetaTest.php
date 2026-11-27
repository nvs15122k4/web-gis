<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class HeadMetaTest extends TestCase
{
    public function test_cac_trang_cong_khai_co_icon_manifest_va_meta(): void
    {
        foreach (['/', '/dang-nhap', '/huong-dan', '/gioi-thieu', '/khong-co-trang-nay'] as $path) {
            $res = $this->get($path);
            $res->assertSee('rel="manifest"', false)
                ->assertSee('/favicon.svg', false)
                ->assertSee('rel="apple-touch-icon"', false)
                ->assertSee('name="theme-color"', false)
                ->assertSee('name="description"', false)
                ->assertSee('property="og:title"', false);
        }
    }

    public function test_ban_do_co_tieu_de_ro_rang_goi_y_lan_dau_va_chu_giai_gon(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<title>Bản đồ biến động bờ sông Hậu', false)
            ->assertSee('id="first-hint"', false)
            ->assertSee('<details class="map-legend legend" id="legend">', false)
            ->assertSee('id="layer-rates" checked', false);
    }

    public function test_file_icon_ton_tai_va_manifest_hop_le(): void
    {
        foreach (['favicon.svg', 'icon-32.png', 'icon-192.png', 'icon-512.png', 'apple-touch-icon.png'] as $f) {
            $this->assertFileExists(public_path($f));
        }
        $m = json_decode(file_get_contents(public_path('manifest.webmanifest')), true);
        $this->assertSame('#2855b8', $m['theme_color']);
        $this->assertCount(3, $m['icons']);
    }
}
