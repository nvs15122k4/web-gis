<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\ThresholdConfig;
use App\Models\Transect;
use App\Models\User;
use Tests\TestCase;

class ApiTest extends TestCase
{
    private function transectId(string $code): int
    {
        return Transect::where('transect_code', $code)->value('id');
    }

    public function test_shorelines_returns_all_years_in_4326(): void
    {
        $r = $this->getJson('/api/shorelines')->assertOk()->assertJsonPath('type', 'FeatureCollection');
        $this->assertCount(8, $r->json('features'));
        $this->assertSame('MultiLineString', $r->json('features.0.geometry.type'));
    }

    public function test_shorelines_filters_by_year_and_multiple_years(): void
    {
        $this->getJson('/api/shorelines?year=2020')->assertOk()->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.year', 2020);
        $this->getJson('/api/shorelines?year[]=2019&year[]=2024')->assertOk()->assertJsonCount(2, 'features');
    }

    public function test_shorelines_missing_year_returns_empty_collection(): void
    {
        $this->getJson('/api/shorelines?year=2022')->assertOk()->assertExactJson(['type' => 'FeatureCollection', 'features' => []]);
    }

    public function test_transects_list_includes_change_class(): void
    {
        $r = $this->getJson('/api/transects')->assertOk();
        $this->assertCount(10, $r->json('features'));
        $this->assertSame('LineString', $r->json('features.0.geometry.type'));
        $this->assertNotNull($r->json('features.0.properties.change_class'));
    }

    public function test_transects_filter_by_change_class(): void
    {
        $r = $this->getJson('/api/transects?change_class=severe_erosion')->assertOk();
        $this->assertCount(3, $r->json('features'));
        $this->assertSame(['severe_erosion'], array_values(array_unique(array_column(array_column($r->json('features'), 'properties'), 'change_class'))));
    }

    public function test_transects_filter_by_bbox(): void
    {
        // Chỉ bao trọn T01 (lat 10.560) và T02 (10.564).
        $this->getJson('/api/transects?bbox=105.19,10.558,105.23,10.566')->assertOk()->assertJsonCount(2, 'features');
        $this->getJson('/api/transects?bbox=106,11,106.1,11.1')->assertOk()->assertJsonCount(0, 'features');
    }

    public function test_transect_detail_has_precomputed_stats(): void
    {
        $id = $this->transectId('T09');
        $this->getJson("/api/transects/{$id}")->assertOk()
            ->assertJsonPath('transect_code', 'T09')
            ->assertJsonPath('stats.change_class', 'severe_erosion')
            ->assertJsonStructure(['stats' => ['nsm_m', 'epr_m_per_year', 'lrr_m_per_year', 'se_lrr', 'r_squared', 'consecutive_erosion_periods', 'u_shoreline_avg']]);
    }

    public function test_transect_detail_404_when_unknown(): void
    {
        $this->getJson('/api/transects/999999')->assertNotFound();
    }

    public function test_transect_history_skips_missing_years(): void
    {
        $full = $this->getJson('/api/transects/'.$this->transectId('T01').'/history')->assertOk();
        $this->assertSame([2018, 2019, 2020, 2021, 2023, 2024, 2025, 2026], array_column($full->json(), 'year'));

        $gap = $this->getJson('/api/transects/'.$this->transectId('T05').'/history')->assertOk();
        $this->assertSame([2018, 2019, 2023, 2024, 2025, 2026], array_column($gap->json(), 'year'));
    }

    public function test_hotspots_filter_by_min_rate(): void
    {
        $this->getJson('/api/erosion-hotspots')->assertOk()->assertJsonCount(2, 'features');
        $this->getJson('/api/erosion-hotspots?min_rate=3')->assertOk()->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.severity', 4.8);
        $this->getJson('/api/erosion-hotspots?min_rate=99')->assertOk()->assertJsonCount(0, 'features');
    }

    public function test_reference_events_with_and_without_bbox(): void
    {
        $this->getJson('/api/reference-events')->assertOk()->assertJsonCount(2, 'features')
            ->assertJsonPath('features.0.geometry.type', 'Point')
            ->assertJsonStructure(['features' => [['properties' => ['event_date', 'description', 'source']]]]);
        // vùng phía nam (T08–T10) không có sự kiện tham chiếu
        $this->getJson('/api/reference-events?bbox=105.19,10.590,105.23,10.600')->assertOk()
            ->assertExactJson(['type' => 'FeatureCollection', 'features' => []]);
    }

    public function test_thresholds_are_public(): void
    {
        $this->getJson('/api/config/thresholds')->assertOk()->assertJsonFragment(['key' => 'min_erosion_rate', 'value' => '1.0']);
    }

    public function test_login_rejects_wrong_password_and_accepts_admin(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'sai'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test'])->assertStatus(422);
        $this->postJson('/api/auth/login', ['email' => 'admin@webgis.test', 'password' => 'password'])
            ->assertOk()->assertJsonStructure(['token', 'user' => ['id', 'email', 'role']]);
    }

    public function test_update_threshold_requires_authentication(): void
    {
        $this->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 2])->assertUnauthorized();
    }

    public function test_update_threshold_forbidden_for_non_admin(): void
    {
        $viewer = User::create(['name' => 'V', 'email' => 'v@webgis.test', 'password' => 'password', 'role' => 'viewer']);
        $this->actingAs($viewer, 'sanctum')->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 2])->assertForbidden();
    }

    public function test_admin_updates_threshold_and_it_is_logged(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 2.5])
            ->assertOk()->assertJsonPath('value', '2.5');

        $this->assertSame('2.5', ThresholdConfig::where('key', 'min_erosion_rate')->value('value'));
        $this->assertSame(1, ActivityLog::where('user_id', $admin->id)->where('action', 'like', 'update_threshold:%')->count());
        $this->getJson('/api/config/thresholds')->assertJsonFragment(['value' => '2.5']);
    }

    public function test_update_threshold_validates_input_and_unknown_key(): void
    {
        $admin = User::where('email', 'admin@webgis.test')->first();
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/config/thresholds/min_erosion_rate', ['value' => 'abc'])->assertStatus(422);
        $this->actingAs($admin, 'sanctum')->putJson('/api/admin/config/thresholds/khong_ton_tai', ['value' => 1])->assertNotFound();
    }

    public function test_status_reports_components_and_layer_counts(): void
    {
        $this->getJson('/api/status')->assertOk()
            ->assertJsonPath('mysql', true)
            ->assertJsonPath('postgis', true)
            ->assertJsonPath('data.shorelines', 8)
            ->assertJsonPath('data.transects', 10)
            ->assertJsonStructure(['geoserver', 'data' => ['hotspots', 'reference_events']]);
    }

    public function test_reference_events_expose_danger_level_length_and_location(): void
    {
        $r = $this->getJson('/api/reference-events')->assertOk();
        $byDate = collect($r->json('features'))->keyBy('properties.event_date');
        $this->assertSame('nguy_hiem', $byDate['2019-08-14']['properties']['danger_level']);
        $this->assertEquals(120, $byDate['2019-08-14']['properties']['length_m']);
        $this->assertSame('Ấp Bình Hòa (fixture)', $byDate['2019-08-14']['properties']['location']);
        // Sự kiện chưa phân loại: trả null để giao diện ghi "Đang cập nhật".
        $this->assertNull($byDate['2020-03-02']['properties']['danger_level']);
    }

    public function test_summary_counts_classes_and_extremes(): void
    {
        $this->getJson('/api/summary')->assertOk()
            ->assertJsonPath('transects.total', 10)
            ->assertJsonPath('transects.significant', 7)
            ->assertJsonPath('transects.by_class', ['accretion' => 2, 'stable' => 3, 'mild_erosion' => 2, 'severe_erosion' => 3])
            ->assertJsonPath('transects.max_erosion.transect_code', 'T10')
            ->assertJsonPath('transects.max_accretion.transect_code', 'T01')
            ->assertJsonPath('shorelines.count', 8)
            ->assertJsonPath('hotspots.count', 2)
            ->assertJsonPath('reference_events.count', 2)
            ->assertJsonPath('reference_events.by_level', ['chua_phan_loai' => 1, 'nguy_hiem' => 1]);
    }

    public function test_summary_is_empty_but_valid_without_spatial_data(): void
    {
        $pg = \Illuminate\Support\Facades\DB::connection('pgsql');
        foreach (['reference_landslide_events', 'erosion_hotspots', 'shoreline_change_stats', 'transect_shoreline_distances', 'transects', 'shorelines'] as $t) {
            $pg->table($t)->delete();
        }
        $this->getJson('/api/summary')->assertOk()
            ->assertJsonPath('transects.total', 0)
            ->assertJsonPath('transects.max_erosion', null)
            ->assertJsonPath('transects.mean_lrr_m_per_year', null)
            ->assertJsonPath('hotspots.length_m', 0);
        $this->assertSame('{}', json_encode($this->getJson('/api/summary')->getData()->reference_events->by_level));
    }

    public function test_export_geojson_contains_stats(): void
    {
        $r = $this->get('/api/export/transects.geojson')->assertOk()
            ->assertHeader('Content-Type', 'application/geo+json')
            ->assertHeader('Content-Disposition', 'attachment; filename="webgis_songhau_transects.geojson"');
        $fc = json_decode($r->getContent(), true);
        $this->assertCount(10, $fc['features']);
        $this->assertSame('LineString', $fc['features'][0]['geometry']['type']);
        $this->assertSame('T01', $fc['features'][0]['properties']['transect_code']);
        $this->assertArrayHasKey('lrr_m_per_year', $fc['features'][0]['properties']);
    }

    public function test_export_csv_has_header_and_wkt(): void
    {
        $csv = $this->get('/api/export/reference.csv')->assertOk()->getContent();
        $lines = array_values(array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));
        $this->assertSame('id,event_date,danger_level,length_m,location,description,source,geom', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('POINT(', $lines[1]);
    }

    public function test_export_rejects_unknown_layer_or_format(): void
    {
        $this->get('/api/export/users.csv')->assertNotFound();
        $this->get('/api/export/transects.shp')->assertNotFound();
    }
}
