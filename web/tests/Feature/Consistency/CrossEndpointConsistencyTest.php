<?php

namespace Tests\Feature\Consistency;

use Tests\TestCase;

/** Cùng một dữ liệu phải cho cùng một con số ở summary / status / transect / hotspot / export. */
class CrossEndpointConsistencyTest extends TestCase
{
    private function export(string $layer): array
    {
        return json_decode($this->get("/api/export/{$layer}.geojson")->getContent(), true)['features'];
    }

    private function csvRows(string $layer): array
    {
        $lines = array_values(array_filter(explode("\n", substr($this->get("/api/export/{$layer}.csv")->getContent(), 3))));

        return array_map('str_getcsv', $lines);
    }

    public function test_status_counts_equal_api_feature_counts(): void
    {
        $s = $this->getJson('/api/status')->json('data');
        $this->assertSame($s['transects'], count($this->getJson('/api/transects')->json('features')));
        $this->assertSame($s['shorelines'], count($this->getJson('/api/shorelines')->json('features')));
        $this->assertSame($s['hotspots'], count($this->getJson('/api/erosion-hotspots')->json('features')));
        $this->assertSame($s['reference_events'], count($this->getJson('/api/reference-events')->json('features')));
    }

    public function test_summary_totals_equal_status_and_lists(): void
    {
        $sum = $this->getJson('/api/summary')->json();
        $st = $this->getJson('/api/status')->json('data');
        $this->assertSame($st['transects'], $sum['transects']['total']);
        $this->assertSame($st['shorelines'], $sum['shorelines']['count']);
        $this->assertSame($st['hotspots'], $sum['hotspots']['count']);
        $this->assertSame($st['reference_events'], $sum['reference_events']['count']);
    }

    public function test_summary_by_class_equals_transect_list_grouping(): void
    {
        $by = array_count_values(array_column(array_column($this->getJson('/api/transects')->json('features'), 'properties'), 'change_class'));
        $sum = $this->getJson('/api/summary')->json('transects.by_class');
        foreach ($sum as $class => $n) {
            $this->assertSame($by[$class] ?? 0, $n, $class);
        }
    }

    public function test_summary_by_class_equals_class_filter_counts(): void
    {
        foreach ($this->getJson('/api/summary')->json('transects.by_class') as $class => $n) {
            $this->assertCount($n, $this->getJson("/api/transects?change_class={$class}")->json('features'), $class);
        }
    }

    public function test_summary_mean_lrr_equals_mean_of_list(): void
    {
        $v = array_column(array_column($this->getJson('/api/transects')->json('features'), 'properties'), 'lrr_m_per_year');
        $this->assertEqualsWithDelta(array_sum($v) / count($v), $this->getJson('/api/summary')->json('transects.mean_lrr_m_per_year'), 0.01);
    }

    public function test_summary_mean_epr_equals_mean_of_list(): void
    {
        $v = array_column(array_column($this->getJson('/api/transects')->json('features'), 'properties'), 'epr_m_per_year');
        $this->assertEqualsWithDelta(array_sum($v) / count($v), $this->getJson('/api/summary')->json('transects.mean_epr_m_per_year'), 0.01);
    }

    public function test_summary_extremes_are_min_and_max_of_list(): void
    {
        $props = array_column($this->getJson('/api/transects')->json('features'), 'properties');
        usort($props, fn ($a, $b) => $a['lrr_m_per_year'] <=> $b['lrr_m_per_year']);
        $sum = $this->getJson('/api/summary')->json('transects');
        $this->assertSame($props[0]['transect_code'], $sum['max_erosion']['transect_code']);
        $this->assertSame(end($props)['transect_code'], $sum['max_accretion']['transect_code']);
        $this->assertSame('T10', $sum['max_erosion']['transect_code']);
        $this->assertSame('T01', $sum['max_accretion']['transect_code']);
    }

    public function test_summary_significant_equals_export_count(): void
    {
        $sig = count(array_filter($this->export('transects'), fn ($f) => $f['properties']['is_significant'] === true));
        $this->assertSame($sig, $this->getJson('/api/summary')->json('transects.significant'));
    }

    public function test_export_transects_match_api_list_by_id_and_stats(): void
    {
        $api = [];
        foreach ($this->getJson('/api/transects')->json('features') as $f) {
            $api[$f['id']] = $f['properties'];
        }
        $this->assertCount(10, $this->export('transects'));
        foreach ($this->export('transects') as $f) {
            $this->assertArrayHasKey($f['id'], $api);
            $this->assertSame($api[$f['id']]['transect_code'], $f['properties']['transect_code']);
            $this->assertSame($api[$f['id']]['change_class'], $f['properties']['change_class']);
            $this->assertEquals($api[$f['id']]['lrr_m_per_year'], $f['properties']['lrr_m_per_year']);
        }
    }

    public function test_export_geometry_equals_api_geometry_for_each_layer(): void
    {
        $map = ['transects' => '/api/transects', 'shorelines' => '/api/shorelines', 'hotspots' => '/api/erosion-hotspots', 'reference' => '/api/reference-events'];
        foreach ($map as $layer => $url) {
            $api = [];
            foreach ($this->getJson($url)->json('features') as $f) {
                $api[$f['id']] = $f['geometry'];
            }
            foreach ($this->export($layer) as $f) {
                $this->assertSame($api[$f['id']], $f['geometry'], "{$layer} #{$f['id']}");
            }
        }
    }

    public function test_csv_row_count_equals_geojson_feature_count(): void
    {
        foreach (['transects', 'shorelines', 'hotspots', 'reference'] as $l) {
            $this->assertCount(count($this->export($l)) + 1, $this->csvRows($l), $l);
        }
    }

    public function test_csv_and_geojson_have_same_ids_in_same_order(): void
    {
        foreach (['transects', 'shorelines', 'hotspots', 'reference'] as $l) {
            $rows = $this->csvRows($l);
            $idCol = array_search('id', $rows[0], true);
            $csv = array_map(fn ($r) => (int) $r[$idCol], array_slice($rows, 1));
            $this->assertSame($csv, array_column($this->export($l), 'id'), $l);
        }
    }

    public function test_hotspot_summary_length_matches_postgis_sum(): void
    {
        $len = \DB::connection('pgsql')->selectOne('SELECT SUM(ST_Length(geom::geography)) AS l FROM erosion_hotspots')->l;
        $this->assertSame((int) round($len), (int) $this->getJson('/api/summary')->json('hotspots.length_m'));
        $this->assertGreaterThan(0, $this->getJson('/api/summary')->json('hotspots.length_m'));
    }

    public function test_summary_years_equal_shoreline_features_years(): void
    {
        $years = array_column(array_column($this->getJson('/api/shorelines')->json('features'), 'properties'), 'year');
        $this->assertSame($years, $this->getJson('/api/summary')->json('shorelines.years'));
    }

    public function test_map_page_available_years_equal_summary_years(): void
    {
        $y = $this->getJson('/api/summary')->json('shorelines.years');
        $html = $this->get('/')->getContent();
        foreach ($y as $year) {
            $this->assertStringContainsString((string) $year, $html);
        }
    }

    public function test_history_has_one_point_per_distance_row_and_matches_nsm(): void
    {
        foreach ($this->getJson('/api/transects')->json('features') as $f) {
            $h = $this->getJson("/api/transects/{$f['id']}/history")->json();
            $stats = $this->getJson("/api/transects/{$f['id']}")->json('stats');
            $n = \DB::connection('pgsql')->table('transect_shoreline_distances')->where('transect_id', $f['id'])->count();
            $this->assertCount($n, $h);
            $this->assertEqualsWithDelta(end($h)['distance_m'] - $h[0]['distance_m'], $stats['nsm_m'], 0.01, $f['properties']['transect_code']);
        }
    }

    public function test_detail_change_class_equals_list_change_class(): void
    {
        foreach ($this->getJson('/api/transects')->json('features') as $f) {
            $this->assertSame($f['properties']['change_class'], $this->getJson("/api/transects/{$f['id']}")->json('stats.change_class'));
        }
    }

    public function test_fixture_sign_convention_erosion_has_negative_lrr(): void
    {
        foreach ($this->getJson('/api/transects')->json('features') as $f) {
            $p = $f['properties'];
            match ($p['change_class']) {
                'accretion' => $this->assertGreaterThan(0, $p['lrr_m_per_year']),
                'mild_erosion', 'severe_erosion' => $this->assertLessThan(0, $p['lrr_m_per_year']),
                default => $this->assertEqualsWithDelta(0, $p['lrr_m_per_year'], 0.5),
            };
        }
    }

    public function test_hotspot_severity_is_positive_erosion_speed_while_lrr_is_negative(): void
    {
        // Ghi nhận quy ước: severity (hotspot) = tốc độ lùi, số dương; LRR của transect tương ứng là số âm.
        foreach ($this->getJson('/api/erosion-hotspots')->json('features') as $f) {
            $this->assertGreaterThan(0, $f['properties']['severity']);
        }
        $this->assertLessThan(0, $this->getJson('/api/summary')->json('transects.max_erosion.lrr_m_per_year'));
    }

    public function test_every_stat_row_has_a_transect_and_vice_versa(): void
    {
        $pg = \DB::connection('pgsql');
        $this->assertSame(0, $pg->table('shoreline_change_stats')->whereNotIn('transect_id', $pg->table('transects')->select('id'))->count());
        $this->assertSame(0, $pg->table('transects')->whereNotIn('id', $pg->table('shoreline_change_stats')->select('transect_id'))->count());
    }

    public function test_transect_without_stats_is_hidden_from_list_but_present_in_export_and_status(): void
    {
        // Ghi nhận: /api/transects dùng INNER JOIN stats, export dùng LEFT JOIN -> lệch số lượng khi thiếu stats.
        $pg = \DB::connection('pgsql');
        $b = $pg->table('baseline')->value('id');
        $pg->statement("INSERT INTO transects (transect_code, baseline_id, geom) VALUES ('ZZ99', ?, ST_GeomFromText('LINESTRING(105.3 10.6, 105.31 10.6)', 4326))", [$b]);
        $this->assertCount(10, $this->getJson('/api/transects')->json('features'));
        $this->assertCount(11, $this->export('transects'));
        $this->assertSame(11, $this->getJson('/api/status')->json('data.transects'));
        $this->assertSame(10, $this->getJson('/api/summary')->json('transects.total'));
        $feat = collect($this->export('transects'))->firstWhere('properties.transect_code', 'ZZ99');
        $this->assertNull($feat['properties']['change_class']);
    }

    public function test_deleting_a_transect_cascades_to_stats_and_distances_everywhere(): void
    {
        $pg = \DB::connection('pgsql');
        $id = $pg->table('transects')->where('transect_code', 'T05')->value('id');
        $pg->table('transects')->where('id', $id)->delete();
        $this->assertSame(0, $pg->table('shoreline_change_stats')->where('transect_id', $id)->count());
        $this->assertSame(0, $pg->table('transect_shoreline_distances')->where('transect_id', $id)->count());
        $this->getJson("/api/transects/{$id}")->assertNotFound();
        $this->assertSame(9, $this->getJson('/api/summary')->json('transects.total'));
        $this->assertCount(9, $this->export('transects'));
        $this->assertSame(9, array_sum($this->getJson('/api/summary')->json('transects.by_class')));
    }
}
