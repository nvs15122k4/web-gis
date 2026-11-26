<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

/** Trang bản đồ có khung đường bờ đối chứng, panel so sánh LRR và chú giải màu ba nguồn. */
class AltSourcePanelTest extends TestCase
{
    private function page(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_layer_control_has_alt_sources_section_with_independence_note(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('id="alt-sources"', $html);
        $this->assertStringContainsString('id="alt-source-list"', $html);
        $this->assertStringContainsString('id="alt-clear"', $html);
        $this->assertStringContainsString('bản đối chứng độc lập, không thay thế bản chính và không phải dự báo sạt lở', $html);
    }

    public function test_transect_inspector_has_three_source_compare_container(): void
    {
        $html = $this->page();
        $this->assertStringContainsString('id="alt-compare"', $html);
        $this->assertStringContainsString('id="alt-compare-body"', $html);
        $this->assertStringContainsString('So sánh ba nguồn dữ liệu (LRR)', $html);
    }

    public function test_legend_lists_the_three_source_colors(): void
    {
        $html = $this->page();
        foreach (['#1565c0' => 'Bản chính', '#00c853' => 'Sentinel-2 Collection 1', '#ff6d00' => 'Landsat 8/9'] as $color => $label) {
            $this->assertMatchesRegularExpression('/background:'.preg_quote($color, '/').'"><\/span>\s*'.preg_quote($label, '/').'/', $html, "{$label} thiếu trong chú giải");
        }
    }

    public function test_existing_year_list_and_compare_sections_are_still_there(): void
    {
        $html = $this->page();
        foreach (['id="year-list"', 'id="compare-a"', 'id="swipe-card"', 'id="layer-rates"'] as $needle) {
            $this->assertStringContainsString($needle, $html);
        }
    }

    public function test_map_page_years_are_unaffected_by_alt_only_year(): void
    {
        // Landsat có năm 2022 nhưng bản chính không có: danh sách năm có dữ liệu của trang chính không được thêm 2022.
        \Illuminate\Support\Facades\DB::connection('pgsql')->statement(
            "INSERT INTO shorelines (year, water_index, source, geom) VALUES (2022, 'MNDWI', 'landsat', ST_Multi(ST_GeomFromText('LINESTRING(105.2 10.5, 105.21 10.5)', 4326)))"
        );
        \Illuminate\Support\Facades\Cache::forget('map.available_years');
        $this->get('/')->assertOk()->assertViewHas('years', fn ($y) => ! in_array(2022, $y['available'], true));
    }
}
