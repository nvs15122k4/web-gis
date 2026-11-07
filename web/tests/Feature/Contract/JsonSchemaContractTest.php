<?php

namespace Tests\Feature\Contract;

use Tests\TestCase;

/** Hợp đồng JSON chặt: đúng tập khoá, đúng kiểu — thêm/bớt khoá sẽ làm test đỏ để buộc cập nhật tài liệu API. */
class JsonSchemaContractTest extends TestCase
{
    private function keys(array $a): array
    {
        $k = array_keys($a);
        sort($k);

        return $k;
    }

    private function assertFeatureCollection(array $fc): void
    {
        $this->assertSame(['features', 'type'], $this->keys($fc));
        $this->assertSame('FeatureCollection', $fc['type']);
        $this->assertTrue(array_is_list($fc['features']));
        foreach ($fc['features'] as $f) {
            $this->assertSame(['geometry', 'id', 'properties', 'type'], $this->keys($f));
            $this->assertSame('Feature', $f['type']);
            $this->assertIsInt($f['id']);
            $this->assertIsArray($f['geometry']);
            $this->assertArrayHasKey('type', $f['geometry']);
            $this->assertArrayHasKey('coordinates', $f['geometry']);
        }
    }

    public function test_status_schema(): void
    {
        $j = $this->getJson('/api/status')->assertOk()->json();
        $this->assertSame(['data', 'geoserver', 'mysql', 'postgis'], $this->keys($j));
        $this->assertIsBool($j['mysql']);
        $this->assertIsBool($j['postgis']);
        $this->assertIsBool($j['geoserver']);
        $this->assertSame(['hotspots', 'reference_events', 'shorelines', 'transects'], $this->keys($j['data']));
        foreach ($j['data'] as $n) {
            $this->assertIsInt($n);
        }
    }

    public function test_summary_schema(): void
    {
        $j = $this->getJson('/api/summary')->assertOk()->json();
        $this->assertSame(['hotspots', 'reference_events', 'shorelines', 'transects'], $this->keys($j));
        $t = $j['transects'];
        $this->assertSame(['by_class', 'max_accretion', 'max_erosion', 'mean_epr_m_per_year', 'mean_lrr_m_per_year', 'significant', 'total'], $this->keys($t));
        $this->assertSame(['accretion', 'mild_erosion', 'severe_erosion', 'stable'], $this->keys($t['by_class']));
        $this->assertIsInt($t['total']);
        $this->assertIsInt($t['significant']);
        $this->assertIsFloat($t['mean_lrr_m_per_year']);
        foreach (['max_accretion', 'max_erosion'] as $k) {
            $this->assertSame(['id', 'lrr_m_per_year', 'transect_code'], $this->keys($t[$k]));
            $this->assertIsInt($t[$k]['id']);
            $this->assertIsString($t[$k]['transect_code']);
            $this->assertIsFloat($t[$k]['lrr_m_per_year']);
        }
        $this->assertSame(['count', 'years'], $this->keys($j['shorelines']));
        $this->assertContainsOnly('int', $j['shorelines']['years']);
        $this->assertSame(['count', 'length_m'], $this->keys($j['hotspots']));
        $this->assertSame(['by_level', 'count'], $this->keys($j['reference_events']));
    }

    public function test_thresholds_schema(): void
    {
        $j = $this->getJson('/api/config/thresholds')->assertOk()->json();
        $this->assertTrue(array_is_list($j));
        foreach ($j as $row) {
            $this->assertSame(['description', 'key', 'value'], $this->keys($row));
            $this->assertIsString($row['key']);
            $this->assertIsString($row['value']);
        }
    }

    public function test_transects_list_schema(): void
    {
        $fc = $this->getJson('/api/transects')->assertOk()->json();
        $this->assertFeatureCollection($fc);
        foreach ($fc['features'] as $f) {
            $this->assertSame('LineString', $f['geometry']['type']);
            $this->assertSame(['baseline_id', 'change_class', 'epr_m_per_year', 'id', 'lrr_m_per_year', 'transect_code'], $this->keys($f['properties']));
            $this->assertIsString($f['properties']['change_class']);
        }
    }

    public function test_transect_detail_schema(): void
    {
        $id = $this->getJson('/api/transects')->json('features.0.id');
        $j = $this->getJson("/api/transects/{$id}")->assertOk()->json();
        $this->assertSame(['id', 'stats', 'transect_code'], $this->keys($j));
        $this->assertSame($id, $j['id']);
        $this->assertSame(['change_class', 'consecutive_erosion_periods', 'epr_m_per_year', 'id', 'is_significant', 'lrr_m_per_year', 'nsm_m', 'r_squared', 'se_lrr', 'transect_id', 'u_shoreline_avg'], $this->keys($j['stats']));
        $this->assertIsBool($j['stats']['is_significant']);
    }

    public function test_transect_history_schema(): void
    {
        $id = $this->getJson('/api/transects')->json('features.0.id');
        $j = $this->getJson("/api/transects/{$id}/history")->assertOk()->json();
        $this->assertTrue(array_is_list($j));
        $this->assertNotEmpty($j);
        foreach ($j as $row) {
            $this->assertSame(['distance_m', 'year'], $this->keys($row));
            $this->assertIsInt($row['year']);
            $this->assertTrue(is_float($row['distance_m']) || is_int($row['distance_m']));
        }
    }

    public function test_shorelines_schema(): void
    {
        $fc = $this->getJson('/api/shorelines')->assertOk()->json();
        $this->assertFeatureCollection($fc);
        foreach ($fc['features'] as $f) {
            $this->assertSame('MultiLineString', $f['geometry']['type']);
            $this->assertSame(['id', 'source_image_date', 'water_index', 'year'], $this->keys($f['properties']));
        }
    }

    public function test_hotspots_schema(): void
    {
        $fc = $this->getJson('/api/erosion-hotspots')->assertOk()->json();
        $this->assertFeatureCollection($fc);
        foreach ($fc['features'] as $f) {
            $this->assertSame(['id', 'notes', 'period_end_year', 'period_start_year', 'severity'], $this->keys($f['properties']));
            $this->assertIsFloat($f['properties']['severity']);
        }
    }

    public function test_reference_events_schema(): void
    {
        $fc = $this->getJson('/api/reference-events')->assertOk()->json();
        $this->assertFeatureCollection($fc);
        foreach ($fc['features'] as $f) {
            $this->assertSame('Point', $f['geometry']['type']);
            foreach (['event_date', 'description', 'source', 'danger_level', 'length_m', 'location'] as $k) {
                $this->assertArrayHasKey($k, $f['properties']);
            }
        }
    }

    public function test_login_success_schema(): void
    {
        $j = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->assertOk()->json();
        $this->assertSame(['token', 'user'], $this->keys($j));
        $this->assertSame(['email', 'id', 'name', 'role'], $this->keys($j['user']));
        $this->assertMatchesRegularExpression('/^\d+\|[A-Za-z0-9]{48}$/', $j['token']);
    }

    public function test_login_failure_schema(): void
    {
        $j = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'x'])->assertStatus(401)->json();
        $this->assertSame(['message'], $this->keys($j));
    }

    public function test_validation_error_schema(): void
    {
        $j = $this->postJson('/api/auth/login', [])->assertStatus(422)->json();
        $this->assertSame(['errors', 'message'], $this->keys($j));
        $this->assertSame(['email', 'password'], $this->keys($j['errors']));
    }

    public function test_threshold_update_schema(): void
    {
        $t = $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])->json('token');
        $j = $this->withToken($t)->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 1.5])->assertOk()->json();
        $this->assertSame(['description', 'key', 'value'], $this->keys($j));
    }

    public function test_forbidden_schema(): void
    {
        $t = $this->postJson('/api/auth/login', ['email' => 'viewer@webgis.test', 'password' => 'password'])->json('token');
        $j = $this->withToken($t)->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 1])->assertForbidden()->json();
        $this->assertSame(['message' => 'Forbidden'], $j);
    }

    public function test_unauthenticated_schema(): void
    {
        $j = $this->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 1])->assertUnauthorized()->json();
        $this->assertSame(['message'], array_keys($j));
    }

    public function test_not_found_schema_is_json_with_message_only(): void
    {
        $j = $this->getJson('/api/transects/999999')->assertNotFound()->json();
        $this->assertSame(['message'], array_keys($j));
    }

    public function test_geojson_export_matches_api_feature_schema(): void
    {
        $fc = json_decode($this->get('/api/export/hotspots.geojson')->getContent(), true);
        $this->assertFeatureCollection($fc);
        $this->assertSame(['id', 'notes', 'period_end_year', 'period_start_year', 'severity'], $this->keys($fc['features'][0]['properties']));
    }
}
