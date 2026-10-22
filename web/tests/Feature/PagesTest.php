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
        $this->get('/gioi-thieu')->assertOk()
            ->assertSee('Tiêu chí phân loại mức biến động')
            ->assertSee('1,96 × SE(LRR)')
            ->assertSee('/api/export/transects.geojson', false);
    }
}
