<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class RateAndYearlyTest extends TestCase
{
    public function test_rate_points_are_points_with_rates(): void
    {
        $r = $this->getJson('/api/rate-points')->assertOk();
        $this->assertSame('FeatureCollection', $r->json('type'));
        foreach ($r->json('features') as $f) {
            $this->assertSame('Point', $f['geometry']['type']);
            $this->assertCount(2, $f['geometry']['coordinates']);
            $this->assertArrayHasKey('lrr_m_per_year', $f['properties']);
            $this->assertArrayHasKey('is_significant', $f['properties']);
        }
    }

    public function test_yearly_stats_are_consistent(): void
    {
        $rows = $this->getJson('/api/yearly-stats')->assertOk()->json();
        foreach ($rows as $i => $row) {
            $this->assertArrayHasKey('mean_delta_m', $row);
            if ($i === 0) {
                $this->assertNull($row['mean_delta_m']);
                $this->assertSame(0.0, (float) $row['cumulative_m']);
            }
            $this->assertLessThanOrEqual($row['transects'], $row['eroding'] + $row['accreting'] + $row['stable']);
        }
        $years = array_column($rows, 'year');
        $sorted = $years;
        sort($sorted);
        $this->assertSame($sorted, $years);
    }
}
