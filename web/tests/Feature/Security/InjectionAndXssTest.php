<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InjectionAndXssTest extends TestCase
{
    public static function payloads(): array
    {
        return array_map(fn ($p) => [$p], [
            "' OR '1'='1",
            "'; DROP TABLE transects; --",
            '1; DELETE FROM shorelines',
            '" OR ""="',
            "x' UNION SELECT NULL,NULL--",
            '<script>alert(1)</script>',
            '"><img src=x onerror=alert(1)>',
            '${jndi:ldap://x}',
            "../../../../etc/passwd",
            "%00",
            "\\' OR 1=1 --",
            '{{7*7}}',
        ]);
    }

    private function assertDataIntact(): void
    {
        $pg = \DB::connection('pgsql');
        $this->assertSame(10, $pg->table('transects')->count());
        $this->assertSame(8, $pg->table('shorelines')->count());
        $this->assertSame(2, $pg->table('erosion_hotspots')->count());
    }

    #[DataProvider('payloads')]
    public function test_change_class_param_is_bound_not_interpolated(string $p): void
    {
        $r = $this->getJson('/api/transects?'.http_build_query(['change_class' => $p]))->assertOk();
        $this->assertSame([], $r->json('features'));
        $this->assertDataIntact();
    }

    #[DataProvider('payloads')]
    public function test_bbox_param_is_cast_to_floats(string $p): void
    {
        $this->getJson('/api/transects?'.http_build_query(['bbox' => $p]))->assertStatus(200);
        $this->getJson('/api/reference-events?'.http_build_query(['bbox' => $p]))->assertStatus(200);
        $this->assertDataIntact();
    }

    #[DataProvider('payloads')]
    public function test_min_rate_param_is_cast_to_float(string $p): void
    {
        $r = $this->getJson('/api/erosion-hotspots?'.http_build_query(['min_rate' => $p]))->assertOk();
        $this->assertCount(2, $r->json('features')); // (float) chuỗi rác = 0 -> lấy mọi hotspot (hành vi hiện tại)
        $this->assertDataIntact();
    }

    #[DataProvider('payloads')]
    public function test_year_param_ignores_non_numeric(string $p): void
    {
        $r = $this->getJson('/api/shorelines?'.http_build_query(['year' => $p]))->assertOk();
        $this->assertCount(8, $r->json('features'));
        $this->assertDataIntact();
    }

    #[DataProvider('payloads')]
    public function test_year_array_with_payload(string $p): void
    {
        $r = $this->getJson('/api/shorelines?'.http_build_query(['year' => [$p, '2020']]))->assertOk();
        $this->assertCount(1, $r->json('features'));
    }

    #[DataProvider('payloads')]
    public function test_threshold_key_in_path_is_not_executed(string $p): void
    {
        $t = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->json('token');
        $r = $this->withToken($t)->putJson('/api/admin/config/thresholds/'.rawurlencode($p), ['value' => 1]);
        $this->assertContains($r->getStatusCode(), [404, 301, 308]);
        $this->assertSame(0, \DB::table('activity_logs')->count());
        $this->assertDataIntact();
    }

    #[DataProvider('payloads')]
    public function test_login_email_payload_rejected(string $p): void
    {
        $r = $this->postJson('/api/auth/login', ['email' => $p, 'password' => $p]);
        $this->assertContains($r->getStatusCode(), [422, 401]);
        $this->assertArrayNotHasKey('token', $r->json());
    }

    public function test_classic_sqli_login_bypass_fails(): void
    {
        $this->postJson('/api/auth/login', ['email' => "admin@webgis.test' --", 'password' => 'x'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => "' OR '1'='1"])->assertStatus(401);
    }

    public function test_array_typed_params_do_not_crash_or_inject(): void
    {
        $this->getJson('/api/transects?change_class[]=severe_erosion&change_class[]=stable')->assertStatus(200);
    }

    public function test_array_bbox_is_ignored(): void
    {
        // Đã sửa: bbox dạng mảng bị bỏ qua như bbox sai khác (trước đây explode() ném TypeError -> 500).
        $this->getJson('/api/transects?bbox[]=1&bbox[]=2')->assertOk();
        $this->getJson('/api/reference-events?bbox[]=1')->assertOk();
    }

    public function test_array_change_class_behaviour_is_documented(): void
    {
        // where('col', array) -> Laravel xử lý như so sánh với mảng: kết quả vẫn là JSON, không có SQL lộ ra.
        $r = $this->getJson('/api/transects?change_class[]=severe_erosion');
        $this->assertStringNotContainsString('SQLSTATE', $r->getContent());
    }

    public function test_xss_payload_in_query_is_never_reflected_in_json(): void
    {
        $x = '<script>alert(1)</script>';
        foreach (['/api/transects?change_class=', '/api/shorelines?year=', '/api/erosion-hotspots?min_rate=', '/api/reference-events?bbox='] as $u) {
            $this->assertStringNotContainsString('<script>', $this->getJson($u.urlencode($x))->getContent());
        }
    }

    public function test_xss_payload_in_query_not_reflected_in_html_pages(): void
    {
        $x = '<script>alert(1)</script>';
        foreach (['/', '/gioi-thieu', '/admin'] as $u) {
            $this->assertStringNotContainsString($x, $this->get($u.'?q='.urlencode($x).'&year='.urlencode($x))->getContent());
        }
    }

    public function test_xss_in_db_text_is_json_encoded_safely_in_api(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->where('severity', 4.8)->update(['notes' => '</script><script>alert(1)</script>']);
        $body = $this->getJson('/api/erosion-hotspots')->getContent();
        $this->assertStringNotContainsString('</script>', $body); // Laravel json_encode escape "/" => "<\/script>"
        $this->assertStringContainsString('<\/script>', $body);
    }

    public function test_xss_in_db_text_csv_export_is_plain_text_attachment(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->where('severity', 4.8)->update(['notes' => '=cmd|calc!A1']);
        $r = $this->get('/api/export/hotspots.csv');
        $this->assertStringStartsWith('attachment', $r->headers->get('Content-Disposition'));
        // Ghi nhận: không chống CSV/formula injection (ô bắt đầu bằng "=" được xuất nguyên văn).
        $this->assertStringContainsString('=cmd|calc!A1', $r->getContent());
    }

    public function test_csv_export_quotes_commas_quotes_and_newlines(): void
    {
        \DB::connection('pgsql')->table('erosion_hotspots')->where('severity', 4.8)->update(['notes' => "a,\"b\"\nc"]);
        $body = $this->get('/api/export/hotspots.csv')->getContent();
        $this->assertStringContainsString("\"a,\"\"b\"\"\nc\"", $body);
    }

    public function test_export_path_traversal_variants_404(): void
    {
        foreach (['..%2f..%2fenv.csv', '%2e%2e/transects.csv', 'transects.csv%00.geojson', 'transects.geojson/../../x'] as $p) {
            $this->get('/api/export/'.$p)->assertStatus(404);
        }
    }

    public function test_numeric_route_params_reject_non_numeric(): void
    {
        foreach (['abc', '1;DROP', '1e3', '-1', '1.5', '%27'] as $id) {
            $this->getJson("/api/transects/{$id}")->assertNotFound();
            $this->getJson("/api/transects/{$id}/history")->assertNotFound();
        }
    }

    public function test_huge_numeric_id_returns_404(): void
    {
        $r = $this->getJson('/api/transects/99999999999999999999999');
        // Đã sửa: route chỉ nhận 1-9 chữ số, id vượt int không khớp route -> 404 (trước đây TypeError -> 500).
        $this->assertSame(404, $r->getStatusCode());
    }

    public function test_very_long_param_value_is_handled(): void
    {
        $this->getJson('/api/transects?change_class='.str_repeat('a', 8000))->assertOk();
    }
}
