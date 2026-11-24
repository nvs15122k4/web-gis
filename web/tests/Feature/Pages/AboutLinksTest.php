<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class AboutLinksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.geoserver.url' => 'http://localhost:8080/geoserver']);
    }

    private function ows(): string
    {
        return rtrim(config('services.geoserver.url'), '/').'/'.config('services.geoserver.workspace');
    }

    public function test_page_links_wms_and_wfs_getcapabilities_using_configured_geoserver(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.$this->ows().'/wms?service=WMS&amp;request=GetCapabilities"', $html);
        $this->assertStringContainsString('href="'.$this->ows().'/wfs?service=WFS&amp;request=GetCapabilities"', $html);
    }

    public function test_khong_cau_hinh_geoserver_thi_an_lien_ket_wms_wfs(): void
    {
        config(['services.geoserver.url' => null]);

        $this->get('/gioi-thieu')->assertOk()
            ->assertDontSee('id="ogc-services"', false)
            ->assertDontSee('Dịch vụ OGC', false)
            ->assertDontSee('/wms?service=WMS', false)
            ->assertSee('/api/export/transects.geojson', false);
    }

    public function test_links_follow_config_changes(): void
    {
        config(['services.geoserver.url' => 'http://gs.example.test:9090/geoserver/', 'services.geoserver.workspace' => 'kiemthu']);

        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString('href="http://gs.example.test:9090/geoserver/kiemthu/wms?service=WMS&amp;request=GetCapabilities"', $html);
        $this->assertStringContainsString('href="http://gs.example.test:9090/geoserver/kiemthu/wfs?service=WFS&amp;request=GetCapabilities"', $html);
        $this->assertStringNotContainsString('localhost:8080', $html);
    }

    public function test_page_renders_when_geoserver_is_unreachable(): void
    {
        config(['services.geoserver.url' => 'http://127.0.0.1:1/geoserver']);

        $this->get('/gioi-thieu')->assertOk()->assertSee('Dịch vụ OGC', false)
            ->assertSee('http://127.0.0.1:1/geoserver/'.config('services.geoserver.workspace').'/wms', false);
    }

    public function test_page_shows_severe_erosion_threshold_with_unicode_minus(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString("\u{2212}2 m/năm", $html);
        $this->assertStringNotContainsString('-2 m/năm', $html, 'Phải dùng dấu trừ U+2212, không dùng gạch nối ASCII');
    }
}
