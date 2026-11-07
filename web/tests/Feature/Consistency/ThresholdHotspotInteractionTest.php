<?php

namespace Tests\Feature\Consistency;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Ngưỡng min_erosion_rate (MySQL) CHỈ là cấu hình hiển thị cho UI: backend không tự áp vào
 * /api/erosion-hotspots (lọc bằng ?min_rate= do client gửi). Các test mô tả hành vi hiện tại.
 */
class ThresholdHotspotInteractionTest extends TestCase
{
    private function admin(): string
    {
        return $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->json('token');
    }

    private function severities(string $qs = ''): array
    {
        $f = $this->getJson('/api/erosion-hotspots'.$qs)->assertOk()->json('features');
        $s = array_column(array_column($f, 'properties'), 'severity');
        sort($s);

        return $s;
    }

    public function test_default_threshold_is_one(): void
    {
        $this->assertSame('1.0', collect($this->getJson('/api/config/thresholds')->json())->firstWhere('key', 'min_erosion_rate')['value']);
    }

    public function test_changing_threshold_does_not_alter_hotspot_endpoint(): void
    {
        $before = $this->severities();
        foreach ([0, 2, 5, 100] as $v) {
            $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => $v])->assertOk();
            $this->assertSame($before, $this->severities());
        }
        $this->assertSame([1.5, 4.8], $before);
    }

    public function test_changing_threshold_does_not_alter_summary_or_status(): void
    {
        $sum = $this->getJson('/api/summary')->json();
        $st = $this->getJson('/api/status')->json('data');
        $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 50])->assertOk();
        $this->assertSame($sum, $this->getJson('/api/summary')->json());
        $this->assertSame($st, $this->getJson('/api/status')->json('data'));
    }

    public function test_changing_threshold_does_not_alter_export(): void
    {
        $a = $this->get('/api/export/hotspots.geojson')->getContent();
        $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 50])->assertOk();
        $this->assertSame($a, $this->get('/api/export/hotspots.geojson')->getContent());
    }

    public function test_changing_threshold_does_not_reclassify_transects(): void
    {
        $a = $this->getJson('/api/summary')->json('transects.by_class');
        $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 10])->assertOk();
        $this->assertSame($a, $this->getJson('/api/summary')->json('transects.by_class'));
    }

    public static function minRates(): array
    {
        return [
            'zero' => ['0', [1.5, 4.8]],
            'below lowest' => ['1.49', [1.5, 4.8]],
            'equal lowest inclusive' => ['1.5', [1.5, 4.8]],
            'just above lowest' => ['1.51', [4.8]],
            'between' => ['3', [4.8]],
            'equal highest inclusive' => ['4.8', [4.8]],
            'above highest' => ['4.81', []],
            'negative returns all' => ['-5', [1.5, 4.8]],
            'scientific notation' => ['1e0', [1.5, 4.8]],
            'comma decimal truncated to 4' => ['4,8', [4.8]],
            'empty ignored' => ['', [1.5, 4.8]],
            'text becomes zero' => ['abc', [1.5, 4.8]],
            'huge' => ['1e308', []],
        ];
    }

    #[DataProvider('minRates')]
    public function test_min_rate_boundaries(string $rate, array $expected): void
    {
        $this->assertSame($expected, $this->severities('?min_rate='.urlencode($rate)));
    }

    public function test_threshold_value_can_be_used_by_client_as_min_rate(): void
    {
        $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 2])->assertOk();
        $v = collect($this->getJson('/api/config/thresholds')->json())->firstWhere('key', 'min_erosion_rate')['value'];
        $this->assertSame([4.8], $this->severities('?min_rate='.$v));
    }

    public function test_min_rate_zero_threshold_is_accepted_by_admin_api(): void
    {
        $this->withToken($this->admin())->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 0])->assertOk()->assertJsonPath('value', '0');
    }

    public function test_hotspot_severity_edit_in_db_is_reflected_immediately_by_filter(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->where('severity', 1.5)->update(['severity' => 6.0]);
        $this->assertEquals([4.8, 6.0], $this->severities());
        $this->assertEquals([6.0], $this->severities('?min_rate=5'));
    }

    public function test_null_severity_hotspot_is_excluded_by_min_rate_but_included_without_it(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->where('severity', 1.5)->update(['severity' => null]);
        $this->assertCount(2, $this->getJson('/api/erosion-hotspots')->json('features'));
        $this->assertSame([4.8], $this->severities('?min_rate=0'));
    }

    public function test_min_rate_combined_with_other_unknown_params_is_ignored(): void
    {
        $this->assertSame([4.8], $this->severities('?min_rate=3&foo=bar&year=2020'));
    }

    public function test_min_rate_given_twice_uses_last_value(): void
    {
        $this->assertSame([4.8], $this->severities('?min_rate=0&min_rate=3'));
    }

    public function test_min_rate_as_array_behaviour(): void
    {
        $r = $this->getJson('/api/erosion-hotspots?min_rate[]=3');
        $this->assertContains($r->getStatusCode(), [200, 500]);
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
    }

    public function test_hotspots_ordering_is_not_guaranteed_but_stable_set(): void
    {
        $ids = array_column($this->getJson('/api/erosion-hotspots')->json('features'), 'id');
        sort($ids);
        $this->assertSame($ids, array_values(array_unique($ids)));
        $this->assertCount(2, $ids);
    }
}
