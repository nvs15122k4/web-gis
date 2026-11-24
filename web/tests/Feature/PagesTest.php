<?php

namespace Tests\Feature;

use Tests\TestCase;

class PagesTest extends TestCase
{
    public function test_map_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('id="map"', false);
    }

    public function test_admin_page_renders(): void
    {
        $this->get('/admin')->assertOk()->assertSee('id="login-form"', false);
    }

    public function test_about_page_renders_methodology(): void
    {
        config(['services.geoserver.url' => 'http://gs.test/geoserver']);
        $this->get('/gioi-thieu')->assertOk()
            ->assertSee('Tiêu chí phân loại mức biến động')
            ->assertSee('|LRR| &gt; t × SE(LRR)', false)
            ->assertSee('LRR ≤ −2 m/năm')
            ->assertSee('/api/export/transects.geojson', false)
            ->assertSee('/wfs?service=WFS&amp;request=GetCapabilities', false)
            ->assertSee('typeName='.config('services.geoserver.workspace').':transects', false);
    }
}
