<?php

namespace Tests\Feature\Consistency;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Ngưỡng min_erosion_rate (PUT) dùng làm ?min_rate= phải khớp số hotspot trong DB ở đúng biên (>=). */
class MinRateBoundaryTest extends TestCase
{
    private function admin(): string
    {
        return $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->json('token');
    }

    private function setThreshold(float|int $x): string
    {
        $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => $x])->assertOk();
        $v = collect($this->getJson('/api/config/thresholds')->json())->firstWhere('key', 'min_erosion_rate')['value'];

        return (string) $v;
    }

    private function apiCount(string $minRate): int
    {
        return count($this->getJson('/api/erosion-hotspots?min_rate='.$minRate)->assertOk()->json('features'));
    }

    private function dbCount(float $min): int
    {
        return DB::connection('pgsql')->table('erosion_hotspots')->where('severity', '>=', $min)->count();
    }

    public function test_threshold_equal_to_hotspot_severity_includes_that_hotspot(): void
    {
        foreach ([1.5, 4.8] as $x) {
            $stored = $this->setThreshold($x);
            $this->assertEqualsWithDelta($x, (float) $stored, 1e-9);
            $this->assertSame($this->dbCount((float) $stored), $this->apiCount($stored), "biên X={$x}");
        }
        $this->assertSame(1, $this->apiCount('4.8'));
        $this->assertSame(2, $this->apiCount('1.5'));
    }

    public function test_threshold_just_below_boundary_matches_db_and_just_above_excludes(): void
    {
        $eps = 0.001;
        $this->assertSame($this->dbCount(4.8 - $eps), $this->apiCount((string) (4.8 - $eps)));
        $this->assertSame(1, $this->apiCount((string) (4.8 - $eps)));
        $this->assertSame(0, $this->apiCount((string) (4.8 + $eps)));
        $this->assertSame(1, $this->apiCount((string) (1.5 + $eps)), 'X+eps loại hotspot 1.5');
        $this->assertSame(2, $this->apiCount((string) (1.5 - $eps)));
    }

    public function test_api_count_equals_db_count_for_many_thresholds(): void
    {
        foreach ([0, 0.5, 1.0, 1.5, 2, 4.8, 5, 100] as $x) {
            $stored = $this->setThreshold($x);
            $this->assertSame($this->dbCount((float) $stored), $this->apiCount($stored), "ngưỡng {$x}");
        }
    }
}
