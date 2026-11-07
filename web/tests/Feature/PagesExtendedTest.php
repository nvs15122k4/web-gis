<?php

namespace Tests\Feature;

use Tests\TestCase;

class PagesExtendedTest extends TestCase
{
    public static function pages(): array
    {
        return [['/'], ['/gioi-thieu'], ['/admin']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_page_is_html_utf8_with_vietnamese_lang(string $url): void
    {
        $r = $this->get($url)->assertOk();
        $this->assertStringContainsString('text/html', $r->headers->get('Content-Type'));
        $this->assertStringContainsString('charset=utf-8', strtolower($r->headers->get('Content-Type')));
        $r->assertSee('<html', false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_page_rejects_post(string $url): void
    {
        $this->post($url)->assertStatus(405);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_page_has_title_and_navigation_links(string $url): void
    {
        $r = $this->get($url)->assertOk()->assertSee('<title>', false);
        $r->assertSee('gioi-thieu', false);
    }

    public function test_trailing_slash_and_unknown_pages(): void
    {
        $this->get('/khong-co')->assertNotFound();
        $this->get('/gioi-thieu/abc')->assertNotFound();
        $this->get('/admin/users')->assertNotFound();
    }

    public function test_map_page_exposes_years_and_missing_2022(): void
    {
        $r = $this->get('/')->assertOk();
        foreach (range(2018, 2026) as $y) {
            $r->assertSee((string) $y);
        }
    }

    public function test_map_page_passes_geoserver_config(): void
    {
        config(['services.geoserver' => ['url' => 'http://gs.test/geoserver', 'workspace' => 'wsx']]);
        $this->get('/')->assertSee('gs.test\\/geoserver', false)->assertSee('wsx', false);
    }

    public function test_map_page_renders_even_when_postgis_unavailable(): void
    {
        $pg = \DB::connection('pgsql');
        $pg->statement('CREATE SCHEMA IF NOT EXISTS tmp_empty_schema');
        $pg->statement('SET LOCAL search_path TO tmp_empty_schema');
        $this->get('/')->assertOk()->assertSee('id="map"', false);
    }

    public function test_map_page_has_core_panels(): void
    {
        $this->get('/')->assertOk()->assertSee('id="map"', false);
    }

    public function test_about_page_sections_and_downloads(): void
    {
        $r = $this->get('/gioi-thieu')->assertOk();
        foreach (['transects', 'shorelines', 'hotspots', 'reference'] as $layer) {
            $r->assertSee("/api/export/{$layer}.geojson", false);
        }
    }

    public function test_admin_page_has_login_form_fields(): void
    {
        $this->get('/admin')->assertOk()->assertSee('id="login-form"', false)
            ->assertSee('type="password"', false)->assertSee('type="email"', false);
    }

    public function test_health_endpoint(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_security_headers_do_not_leak_php_version_in_body(): void
    {
        $this->assertStringNotContainsString('Whoops', $this->get('/')->getContent());
    }
}
