<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class AboutStepsTest extends TestCase
{
    public function test_trang_gioi_thieu_co_tam_buoc_giai_doan_anh(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString('id="giai-doan-anh"', $html);
        $this->assertSame(8, substr_count($html, 'class="step"'));
        foreach (range(1, 8) as $n) {
            $this->assertStringContainsString('id="buoc-'.$n.'"', $html);
        }
    }

    public function test_moi_buoc_co_hinh_that_va_mo_ta_thay_the(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        preg_match_all('#<img src="(/img/phuong-phap/[^"]+)"[^>]* alt="([^"]+)"#', $html, $m);
        $this->assertCount(8, $m[1]);
        foreach ($m[1] as $i => $src) {
            $this->assertFileExists(public_path($src));
            $this->assertNotSame('', trim($m[2][$i]));
        }
    }

    public function test_so_lieu_khop_voi_dau_ra_pipeline(): void
    {
        $summary = json_decode((string) file_get_contents(base_path('../pipeline/output/summary.json')), true);
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString((string) $summary['transects'].' transect', $html);
        $c = $summary['classes'];
        $this->assertStringContainsString("{$c['accretion']} bồi, {$c['stable']} ổn định, {$c['mild_erosion']} lùi nhẹ, {$c['severe_erosion']} lùi mạnh", $html);
    }

    public function test_dau_tru_dung_unicode_trong_cac_buoc(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString("\u{2212}2,56 m/năm", $html);
    }
}
