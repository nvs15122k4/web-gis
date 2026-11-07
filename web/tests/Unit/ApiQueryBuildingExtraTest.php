<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ErosionHotspotController;
use App\Http\Controllers\Api\ReferenceEventController;
use App\Http\Controllers\Api\ShorelineController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\Api\TransectController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;

/** Kiểm tra câu SQL + binding mà controller dựng nên (pretend, không chạm PostGIS). */
class ApiQueryBuildingExtraTest extends PretendQueryCase
{
    private function req(array $query): Request
    {
        return Request::create('/api/x', 'GET', $query);
    }

    private function sqlOf(callable $fn): array
    {
        $r = $this->capture($fn);
        $this->assertNull($r['error'], (string) $r['error']);
        $this->assertNotEmpty($r['queries']);

        return $r['queries'][0];
    }

    // ---- TransectController::index ----

    public function test_transect_index_khong_loc_khong_co_where_va_envelope(): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req([])));
        $this->assertStringContainsString('inner join "shoreline_change_stats" as "s"', $q['sql']);
        $this->assertStringNotContainsString('ST_MakeEnvelope', $q['sql']);
        $this->assertStringNotContainsString('"s"."change_class" =', $q['sql']);
        $this->assertSame([], $q['bindings']);
    }

    public function test_transect_index_chon_cot_thong_ke_va_geojson(): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req([])));
        foreach (['"transects".*', 'ST_AsGeoJSON(transects.geom, 6) AS geojson', '"s"."change_class"', '"s"."epr_m_per_year"', '"s"."lrr_m_per_year"'] as $frag) {
            $this->assertStringContainsString($frag, $q['sql']);
        }
    }

    public function test_transect_index_loc_change_class(): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req(['change_class' => 'severe_erosion'])));
        $this->assertStringContainsString('"s"."change_class" = ?', $q['sql']);
        $this->assertSame(['severe_erosion'], $q['bindings']);
    }

    public function test_transect_index_change_class_rong_bi_bo_qua(): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req(['change_class' => ''])));
        $this->assertStringNotContainsString('change_class" =', $q['sql']);
    }

    public static function validBboxes(): array
    {
        return [
            'chuan' => ['105.9,9.5,106.2,9.8', [105.9, 9.5, 106.2, 9.8]],
            'am' => ['-1.5,-2,3,4', [-1.5, -2.0, 3.0, 4.0]],
            'so nguyen' => ['1,2,3,4', [1.0, 2.0, 3.0, 4.0]],
            'ky hieu khoa hoc' => ['1e1,2,3,4', [10.0, 2.0, 3.0, 4.0]],
            'phan tu khong phai so thanh 0' => ['a,b,c,d', [0.0, 0.0, 0.0, 0.0]],
            'khoang trang' => [' 1, 2 ,3,4', [1.0, 2.0, 3.0, 4.0]],
        ];
    }

    #[DataProvider('validBboxes')]
    public function test_transect_index_bbox_hop_le_thanh_envelope(string $bbox, array $expected): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req(['bbox' => $bbox])));
        $this->assertStringContainsString('transects.geom && ST_MakeEnvelope(?, ?, ?, ?, 4326)', $q['sql']);
        $this->assertSame($expected, $q['bindings']);
    }

    public static function invalidBboxes(): array
    {
        return [
            '3 phan tu' => ['1,2,3'],
            '5 phan tu' => ['1,2,3,4,5'],
            '1 phan tu' => ['1'],
            'chuoi rong' => [''],
            'phan cach sai' => ['1;2;3;4'],
        ];
    }

    #[DataProvider('invalidBboxes')]
    public function test_transect_index_bbox_sai_so_phan_tu_bi_bo_qua(string $bbox): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req(['bbox' => $bbox])));
        $this->assertStringNotContainsString('ST_MakeEnvelope', $q['sql']);
    }

    public function test_transect_index_ket_hop_class_va_bbox_theo_thu_tu_binding(): void
    {
        $q = $this->sqlOf(fn () => (new TransectController)->index($this->req(['change_class' => 'stable', 'bbox' => '1,2,3,4'])));
        $this->assertSame(['stable', 1.0, 2.0, 3.0, 4.0], $q['bindings']);
    }

    public function test_transect_index_tra_feature_collection_rong_khi_khong_co_dong(): void
    {
        $r = $this->capture(fn () => (new TransectController)->index($this->req([])));
        $this->assertSame(['type' => 'FeatureCollection', 'features' => []], $r['result']->getData(true));
    }

    public function test_transect_show_va_history_khong_thay_thi_404(): void
    {
        foreach (['show', 'history'] as $m) {
            $r = $this->capture(fn () => (new TransectController)->$m(999));
            $this->assertInstanceOf(ModelNotFoundException::class, $r['error'], $m);
            $this->assertSame([999], $r['error']->getIds());
        }
    }

    public function test_transect_show_eager_load_stats_va_history_eager_load_distances(): void
    {
        $show = $this->capture(fn () => (new TransectController)->show(5));
        $this->assertStringContainsString('from "transects" where "transects"."id" = ?', $show['queries'][0]['sql']);
        $this->assertSame([5], $show['queries'][0]['bindings']);
        $this->assertCount(1, $show['queries']); // không có dòng thì không eager-load tiếp
    }

    // ---- ShorelineController ----

    public static function yearFilters(): array
    {
        return [
            'mot nam' => [['year' => '2020'], [2020 => '2020']],
            'nhieu nam' => [['year' => ['2019', '2024']], ['2019', '2024']],
            'bo phan tu khong phai so' => [['year' => ['2019', 'abc', '2024']], ['2019', '2024']],
            'toan rac' => [['year' => 'abc'], null],
            'khong co year' => [[], null],
            'nam am van la so' => [['year' => '-5'], ['-5']],
            'so thap phan' => [['year' => '2020.5'], ['2020.5']],
            'chuoi rong' => [['year' => ''], null],
        ];
    }

    #[DataProvider('yearFilters')]
    public function test_shoreline_loc_nam(array $query, ?array $expectedBindings): void
    {
        $q = $this->sqlOf(fn () => (new ShorelineController)->index($this->req($query)));
        if ($expectedBindings === null) {
            $this->assertStringNotContainsString('"year" in', $q['sql']);
            $this->assertSame([], $q['bindings']);
        } else {
            $this->assertStringContainsString('"year" in', $q['sql']);
            $this->assertEquals(array_values($expectedBindings), $q['bindings']);
        }
        $this->assertStringContainsString('order by "year" asc', $q['sql']);
    }

    // ---- ErosionHotspotController ----

    public static function minRates(): array
    {
        return [
            'so duong' => ['1.5', 1.5],
            'zero khong bi bo qua' => ['0', 0.0],
            'am' => ['-2', -2.0],
            'chuoi rac thanh 0' => ['abc', 0.0],
        ];
    }

    #[DataProvider('minRates')]
    public function test_hotspot_min_rate(string $in, float $expected): void
    {
        $q = $this->sqlOf(fn () => (new ErosionHotspotController)->index($this->req(['min_rate' => $in])));
        $this->assertStringContainsString('"severity" >= ?', $q['sql']);
        $this->assertSame([$expected], $q['bindings']);
    }

    public function test_hotspot_khong_co_min_rate_hoac_rong_thi_khong_where(): void
    {
        foreach ([[], ['min_rate' => '']] as $query) {
            $q = $this->sqlOf(fn () => (new ErosionHotspotController)->index($this->req($query)));
            $this->assertStringNotContainsString('where', $q['sql']);
        }
    }

    // ---- ReferenceEventController ----

    public function test_reference_events_sap_theo_ngay_va_khong_loc_khi_khong_bbox(): void
    {
        $q = $this->sqlOf(fn () => (new ReferenceEventController)->index($this->req([])));
        $this->assertStringContainsString('order by "event_date" asc', $q['sql']);
        $this->assertStringNotContainsString('ST_MakeEnvelope', $q['sql']);
    }

    public function test_reference_events_bbox_hop_le_va_khong_hop_le(): void
    {
        $ok = $this->sqlOf(fn () => (new ReferenceEventController)->index($this->req(['bbox' => '1,2,3,4'])));
        $this->assertStringContainsString('reference_landslide_events.geom && ST_MakeEnvelope', $ok['sql']);
        $this->assertSame([1.0, 2.0, 3.0, 4.0], $ok['bindings']);

        $bad = $this->sqlOf(fn () => (new ReferenceEventController)->index($this->req(['bbox' => '1,2'])));
        $this->assertStringNotContainsString('ST_MakeEnvelope', $bad['sql']);
    }

    public function test_reference_events_rong_tra_feature_collection_hop_le(): void
    {
        $r = $this->capture(fn () => (new ReferenceEventController)->index($this->req([])));
        $this->assertSame('FeatureCollection', $r['result']->getData(true)['type']);
        $this->assertSame([], $r['result']->getData(true)['features']);
    }

    // ---- SummaryController::index (truy vấn dựng ra) ----

    public function test_summary_index_phat_ra_dung_cac_truy_van_tong_hop(): void
    {
        $r = $this->capture(fn () => (new SummaryController)->index());
        $sql = implode("\n", array_column($r['queries'], 'sql'));
        $this->assertStringContainsString('group by "change_class"', $sql);
        $this->assertStringContainsString('AVG(lrr_m_per_year)', $sql);
        $this->assertStringContainsString('AVG(epr_m_per_year)', $sql);
        $this->assertStringContainsString('select distinct "year"', $sql);
        $this->assertStringContainsString("COALESCE(danger_level, 'chua_phan_loai')", $sql);
        $this->assertStringContainsString('COALESCE(SUM(ST_Length(geom::geography)), 0)', $sql);
        $this->assertCount(5, $r['queries']); // 2 truy vấn cực trị chỉ chạy sau khi dựng $total (pretend trả null nên dừng sớm)
    }

    public function test_summary_hang_so_classes_dung_thu_tu_va_khong_trung(): void
    {
        $c = (new \ReflectionClass(SummaryController::class))->getConstant('CLASSES');
        $this->assertSame(['accretion', 'stable', 'mild_erosion', 'severe_erosion'], $c);
        $this->assertCount(count($c), array_unique($c));
    }
}
