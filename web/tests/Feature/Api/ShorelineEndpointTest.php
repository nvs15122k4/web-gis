<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ShorelineEndpointTest extends TestCase
{
    public static function years(): array
    {
        return array_map(fn ($y) => [$y], [2018, 2019, 2020, 2021, 2023, 2024, 2025, 2026]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('years')]
    public function test_each_available_year_returns_exactly_one_feature(int $year): void
    {
        $r = $this->getJson("/api/shorelines?year={$year}")->assertOk()->assertJsonCount(1, 'features');
        $this->assertSame($year, $r->json('features.0.properties.year'));
        $this->assertSame('MNDWI', $r->json('features.0.properties.water_index'));
    }

    public static function emptyYears(): array
    {
        return [
            'năm thiếu 2022' => ['2022'],
            'năm quá khứ' => ['1990'],
            'năm tương lai' => ['2099'],
            'không phải số bị bỏ qua nên trả tất cả' => ['abc', 8],
            'chuỗi rỗng trả tất cả' => ['', 8],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('emptyYears')]
    public function test_year_filter_edge_cases(string $year, int $expected = 0): void
    {
        $this->getJson('/api/shorelines?year='.urlencode($year))->assertOk()
            ->assertJsonPath('type', 'FeatureCollection')->assertJsonCount($expected, 'features');
    }

    public function test_features_are_ordered_by_year_ascending(): void
    {
        $years = array_column(array_column($this->getJson('/api/shorelines')->json('features'), 'properties'), 'year');
        $sorted = $years;
        sort($sorted);
        $this->assertSame($sorted, $years);
    }

    public function test_geometry_is_valid_multilinestring_in_vietnam_bounds(): void
    {
        foreach ($this->getJson('/api/shorelines')->json('features') as $f) {
            $this->assertSame('Feature', $f['type']);
            $this->assertSame('MultiLineString', $f['geometry']['type']);
            foreach ($f['geometry']['coordinates'] as $line) {
                foreach ($line as [$lng, $lat]) {
                    $this->assertGreaterThan(105, $lng);
                    $this->assertLessThan(106, $lng);
                    $this->assertGreaterThan(10, $lat);
                    $this->assertLessThan(11, $lat);
                }
            }
        }
    }

    public function test_properties_do_not_leak_raw_geometry_columns(): void
    {
        $p = $this->getJson('/api/shorelines?year=2020')->json('features.0.properties');
        $this->assertArrayNotHasKey('geom', $p);
        $this->assertArrayNotHasKey('geojson', $p);
        $this->assertArrayHasKey('source_image_date', $p);
    }

    public function test_mixed_valid_and_invalid_years_only_use_numeric(): void
    {
        $this->getJson('/api/shorelines?year[]=2019&year[]=abc&year[]=2022')->assertOk()->assertJsonCount(1, 'features');
    }

    public function test_feature_id_matches_primary_key_and_is_unique(): void
    {
        $ids = array_column($this->getJson('/api/shorelines')->json('features'), 'id');
        $this->assertCount(8, array_unique($ids));
    }

    public function test_post_method_not_allowed(): void
    {
        $this->postJson('/api/shorelines')->assertStatus(405);
    }
}
