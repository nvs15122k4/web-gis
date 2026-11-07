<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class HotspotAndReferenceTest extends TestCase
{
    public static function minRates(): array
    {
        return [
            ['0', 2], ['1.5', 2], ['1.51', 1], ['3', 1], ['4.8', 1], ['4.81', 0], ['99', 0], ['-5', 2], ['abc', 2], ['', 2],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('minRates')]
    public function test_hotspot_min_rate(string $rate, int $count): void
    {
        $this->getJson('/api/erosion-hotspots?min_rate='.urlencode($rate))->assertOk()->assertJsonCount($count, 'features');
    }

    public function test_hotspot_feature_shape(): void
    {
        $f = $this->getJson('/api/erosion-hotspots')->json('features.0');
        $this->assertSame('LineString', $f['geometry']['type']);
        foreach (['severity', 'period_start_year', 'period_end_year', 'notes'] as $k) {
            $this->assertArrayHasKey($k, $f['properties']);
        }
        $this->assertSame(2018, $f['properties']['period_start_year']);
        $this->assertSame(2026, $f['properties']['period_end_year']);
    }

    public function test_hotspots_empty_when_table_empty_is_valid_collection(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->delete();
        $this->getJson('/api/erosion-hotspots')->assertOk()->assertExactJson(['type' => 'FeatureCollection', 'features' => []]);
    }

    public static function refBboxes(): array
    {
        return [
            'toàn vùng' => ['105.19,10.55,105.23,10.61', 2],
            'phía bắc' => ['105.19,10.559,105.23,10.567', 2],
            'phía nam' => ['105.19,10.590,105.23,10.600', 0],
            'ngoài vùng' => ['0,0,1,1', 0],
            'bbox sai số phần tử' => ['1,2', 2],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refBboxes')]
    public function test_reference_bbox(string $bbox, int $count): void
    {
        $this->getJson('/api/reference-events?bbox='.$bbox)->assertOk()->assertJsonCount($count, 'features');
    }

    public function test_reference_ordered_by_event_date(): void
    {
        $dates = array_column(array_column($this->getJson('/api/reference-events')->json('features'), 'properties'), 'event_date');
        $this->assertSame(['2019-08-14', '2020-03-02'], $dates);
    }

    public function test_reference_unclassified_event_has_nulls(): void
    {
        $p = $this->getJson('/api/reference-events')->json('features.1.properties');
        $this->assertNull($p['danger_level']);
        $this->assertNull($p['length_m']);
        $this->assertNull($p['location']);
    }

    public function test_reference_empty_table_returns_valid_collection(): void
    {
        \DB::connection('pgsql')->table('reference_landslide_events')->delete();
        $this->getJson('/api/reference-events')->assertOk()->assertExactJson(['type' => 'FeatureCollection', 'features' => []]);
    }
}
