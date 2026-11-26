<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** ?source= của /api/shorelines, /api/alt-sources, /api/transects/{id}/compare và các chỗ phải bỏ qua bản đối chứng. */
class AltSourceApiTest extends TestCase
{
    private function pg()
    {
        return DB::connection('pgsql');
    }

    private function altShoreline(string $source, int $year): void
    {
        $this->pg()->statement(
            "INSERT INTO shorelines (year, water_index, source, geom) VALUES (?, 'MNDWI', ?, ST_Multi(ST_GeomFromText('LINESTRING(105.21 10.56, 105.21 10.60)', 4326)))",
            [$year, $source]
        );
    }

    private function seedAlt(): int
    {
        $this->altShoreline('s2c1', 2020);
        $this->altShoreline('s2c1', 2018);
        $this->altShoreline('landsat', 2020);
        $this->altShoreline('landsat', 2022); // bản chính không có 2022
        $tid = $this->pg()->table('transects')->where('transect_code', 'T08')->value('id');
        $this->pg()->table('transect_alt_lrr')->insert([
            ['transect_id' => $tid, 'source' => 's2c1', 'lrr_m_per_year' => -3.1, 'change_class' => 'severe_erosion', 'diff_vs_main' => 0.4],
            ['transect_id' => $tid, 'source' => 'landsat', 'lrr_m_per_year' => -3.6, 'change_class' => 'severe_erosion', 'diff_vs_main' => -0.1],
        ]);
        $this->pg()->table('alt_source_years')->insert([
            ['source' => 's2c1', 'year' => 2018, 'scenes_used' => 2, 'low_confidence' => true],
            ['source' => 's2c1', 'year' => 2020, 'scenes_used' => 10, 'low_confidence' => false],
            ['source' => 's2c1', 'year' => 2022, 'scenes_used' => 0, 'low_confidence' => true],
            ['source' => 'landsat', 'year' => 2020, 'scenes_used' => 6, 'low_confidence' => false],
        ]);

        return $tid;
    }

    // ---- /api/shorelines ----

    public function test_default_returns_only_main_even_when_alt_rows_exist(): void
    {
        $before = $this->getJson('/api/shorelines')->json();
        $this->seedAlt();
        $after = $this->getJson('/api/shorelines')->json();
        $this->assertSame($before, $after);
        $this->assertCount(8, $after['features']);
    }

    public function test_main_features_keep_old_property_set_without_source(): void
    {
        $this->seedAlt();
        $p = $this->getJson('/api/shorelines?year=2020')->json('features.0.properties');
        $this->assertArrayNotHasKey('source', $p);
        $this->assertSame('MNDWI', $p['water_index']);
    }

    public function test_explicit_source_main_equals_default(): void
    {
        $this->seedAlt();
        $this->assertSame($this->getJson('/api/shorelines')->json(), $this->getJson('/api/shorelines?source=main')->json());
    }

    public function test_source_s2c1_returns_only_s2c1_ordered_by_year_with_source_property(): void
    {
        $this->seedAlt();
        $r = $this->getJson('/api/shorelines?source=s2c1')->assertOk()->assertJsonCount(2, 'features');
        $this->assertSame([2018, 2020], array_column(array_column($r->json('features'), 'properties'), 'year'));
        foreach ($r->json('features') as $f) {
            $this->assertSame('s2c1', $f['properties']['source']);
            $this->assertSame('MultiLineString', $f['geometry']['type']);
        }
    }

    public function test_source_landsat_with_year_filter(): void
    {
        $this->seedAlt();
        $this->getJson('/api/shorelines?source=landsat&year=2022')->assertOk()->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.properties.year', 2022);
        $this->getJson('/api/shorelines?source=s2c1&year=2022')->assertOk()->assertJsonCount(0, 'features');
        $this->getJson('/api/shorelines?year=2022')->assertOk()->assertJsonCount(0, 'features'); // bản chính vẫn thiếu 2022
    }

    public static function badSources(): array
    {
        return [['jrc'], ['MAIN'], ['s2c1;DROP TABLE shorelines'], ["' OR 1=1 --"]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('badSources')]
    public function test_unknown_source_is_422_with_allowed_list(string $source): void
    {
        $this->getJson('/api/shorelines?source='.urlencode($source))->assertStatus(422)
            ->assertJsonPath('allowed', ['main', 's2c1', 'landsat']);
        $this->assertGreaterThan(0, $this->pg()->table('shorelines')->count()); // bảng còn nguyên
    }

    public function test_empty_source_falls_back_to_main_like_empty_year(): void
    {
        $this->seedAlt();
        $this->assertSame($this->getJson('/api/shorelines')->json(), $this->getJson('/api/shorelines?source=')->json());
    }

    public function test_source_given_as_array_is_422(): void
    {
        $this->getJson('/api/shorelines?source[]=s2c1')->assertStatus(422);
    }

    // ---- những nơi phải bỏ qua bản đối chứng ----

    public function test_summary_status_export_and_map_years_ignore_alt_rows(): void
    {
        $summary = $this->getJson('/api/summary')->json();
        $status = $this->getJson('/api/status')->json('data.shorelines');
        $csv = $this->get('/api/export/shorelines.csv')->getContent();
        $geo = $this->get('/api/export/shorelines.geojson')->json('features');
        $mapYears = $this->get('/')->assertOk()->getContent();

        $this->seedAlt();

        $this->assertSame($summary, $this->getJson('/api/summary')->json());
        $this->assertSame($status, $this->getJson('/api/status')->json('data.shorelines'));
        $this->assertSame($csv, $this->get('/api/export/shorelines.csv')->getContent());
        $this->assertCount(count($geo), $this->get('/api/export/shorelines.geojson')->json('features'));
        $this->assertSame($mapYears, $this->get('/')->getContent());
        $this->assertNotContains(2022, $this->getJson('/api/summary')->json('years') ?? []);
    }

    // ---- /api/alt-sources ----

    public function test_alt_sources_lists_years_colors_and_low_confidence(): void
    {
        $this->seedAlt();
        $r = $this->getJson('/api/alt-sources')->assertOk();
        $r->assertJsonPath('main.color', '#1565c0')->assertJsonPath('main.key', 'main');
        $this->assertSame([2018, 2019, 2020, 2021, 2023, 2024, 2025, 2026], $r->json('main.years'));
        $s = collect($r->json('sources'))->keyBy('key');
        $this->assertSame('#00c853', $s['s2c1']['color']);
        $this->assertSame('#ff6d00', $s['landsat']['color']);
        $this->assertSame([2018, 2020], $s['s2c1']['years']);
        $this->assertSame([2020, 2022], $s['landsat']['years']);
        $this->assertSame([2018, 2022], $s['s2c1']['low_confidence_years']);
        $this->assertSame([], $s['landsat']['low_confidence_years']);
        $this->assertSame(10, $s['s2c1']['scenes_used']['2020']);
        $this->assertStringContainsString('đối chứng độc lập', $r->json('note'));
    }

    public function test_alt_sources_empty_when_nothing_imported(): void
    {
        $r = $this->getJson('/api/alt-sources')->assertOk();
        foreach ($r->json('sources') as $s) {
            $this->assertSame([], $s['years']);
            $this->assertSame([], $s['low_confidence_years']);
        }
    }

    public function test_alt_sources_is_cached_like_other_data_endpoints_and_get_only(): void
    {
        $this->assertStringContainsString('s-maxage=300', $this->getJson('/api/alt-sources')->headers->get('Cache-Control'));
        $this->postJson('/api/alt-sources')->assertStatus(405);
    }

    // ---- /api/transects/{id}/compare ----

    public function test_compare_returns_three_sources_for_a_transect(): void
    {
        $tid = $this->seedAlt();
        $r = $this->getJson("/api/transects/{$tid}/compare")->assertOk();
        $r->assertJsonPath('transect_code', 'T08')->assertJsonPath('main.key', 'main')->assertJsonPath('main.color', '#1565c0')
            ->assertJsonPath('main.change_class', 'severe_erosion');
        $this->assertEqualsWithDelta(-3.5, $r->json('main.lrr_m_per_year'), 0.01); // lấy từ shoreline_change_stats
        $s = collect($r->json('sources'))->keyBy('key');
        $this->assertEqualsWithDelta(-3.1, $s['s2c1']['lrr_m_per_year'], 1e-9);
        $this->assertEqualsWithDelta(0.4, $s['s2c1']['diff_vs_main'], 1e-9);
        $this->assertSame([2018, 2022], $s['s2c1']['low_confidence_years']);
        $this->assertEqualsWithDelta(-3.6, $s['landsat']['lrr_m_per_year'], 1e-9);
        $this->assertSame('severe_erosion', $s['landsat']['change_class']);
        $this->assertStringContainsString('không phải dự báo sạt lở', $r->json('note'));
    }

    public function test_compare_transect_without_alt_rows_has_null_values_not_error(): void
    {
        $this->seedAlt();
        $tid = $this->pg()->table('transects')->where('transect_code', 'T01')->value('id');
        $r = $this->getJson("/api/transects/{$tid}/compare")->assertOk();
        foreach ($r->json('sources') as $s) {
            $this->assertNull($s['lrr_m_per_year']);
            $this->assertNull($s['change_class']);
        }
        $this->assertEqualsWithDelta(2.5, $r->json('main.lrr_m_per_year'), 0.01);
    }

    public function test_compare_unknown_transect_is_404_and_bad_id_is_not_routed(): void
    {
        $this->getJson('/api/transects/99999999/compare')->assertNotFound();
        $this->getJson('/api/transects/abc/compare')->assertNotFound();
    }

    public function test_existing_transect_endpoints_unchanged_by_alt_rows(): void
    {
        $tid = $this->pg()->table('transects')->where('transect_code', 'T08')->value('id');
        $a = [$this->getJson('/api/transects')->json(), $this->getJson("/api/transects/{$tid}")->json(), $this->getJson("/api/transects/{$tid}/history")->json()];
        $this->seedAlt();
        $b = [$this->getJson('/api/transects')->json(), $this->getJson("/api/transects/{$tid}")->json(), $this->getJson("/api/transects/{$tid}/history")->json()];
        $this->assertSame($a, $b);
    }
}
