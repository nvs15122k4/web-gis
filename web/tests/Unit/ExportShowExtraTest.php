<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ExportController;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;

/** ExportController::show với DB mock: header, tên file, định dạng, SQL sinh ra. */
class ExportShowExtraTest extends BaseUnitTest
{
    private ?string $lastSql = null;

    private function show(string $layer, string $format, array $rows = []): \Illuminate\Http\Response
    {
        $conn = Mockery::mock(Connection::class);
        $conn->shouldReceive('select')->once()->andReturnUsing(function ($sql) use ($rows) {
            $this->lastSql = $sql;

            return array_map(fn ($r) => (object) $r, $rows);
        });
        DB::shouldReceive('connection')->with('pgsql')->andReturn($conn);

        return (new ExportController)->show($layer, $format);
    }

    public static function layerFormats(): array
    {
        $o = [];
        foreach (['transects' => ['t', 't.transect_code'], 'shorelines' => ['l', 'l.year'], 'hotspots' => ['h', 'h.id'], 'reference' => ['r', 'r.event_date']] as $layer => [$alias, $order]) {
            foreach (['geojson', 'csv'] as $fmt) {
                $o["$layer.$fmt"] = [$layer, $fmt, $alias, $order];
            }
        }

        return $o;
    }

    #[DataProvider('layerFormats')]
    public function test_sql_va_header_theo_lop_va_dinh_dang(string $layer, string $fmt, string $alias, string $order): void
    {
        $res = $this->show($layer, $fmt);
        $geom = $fmt === 'csv' ? "ST_AsText($alias.geom)" : "ST_AsGeoJSON($alias.geom, 6)";
        $this->assertStringContainsString(", $geom AS geom FROM ", $this->lastSql);
        $this->assertStringEndsWith(" ORDER BY $order", $this->lastSql);
        $this->assertSame(1, substr_count($this->lastSql, 'AS geom'));
        $this->assertSame("attachment; filename=\"webgis_songhau_{$layer}.{$fmt}\"", $res->headers->get('Content-Disposition'));
        $this->assertSame(200, $res->getStatusCode());
        $this->assertStringContainsString($fmt === 'csv' ? 'text/csv; charset=UTF-8' : 'application/geo+json', $res->headers->get('Content-Type'));
    }

    public function test_geojson_rong_la_feature_collection_hop_le(): void
    {
        $body = json_decode($this->show('shorelines', 'geojson')->getContent(), true);
        $this->assertSame(['type' => 'FeatureCollection', 'features' => []], $body);
    }

    public function test_geojson_tach_geom_khoi_properties_va_giai_ma_geometry(): void
    {
        $row = ['id' => 4, 'year' => 2020, 'water_index' => 'MNDWI', 'source_image_date' => null, 'geom' => '{"type":"Point","coordinates":[106.1,9.7]}'];
        $f = json_decode($this->show('shorelines', 'geojson', [$row])->getContent(), true)['features'][0];
        $this->assertSame(4, $f['id']);
        $this->assertSame('Feature', $f['type']);
        $this->assertSame(['type' => 'Point', 'coordinates' => [106.1, 9.7]], $f['geometry']);
        $this->assertArrayNotHasKey('geom', $f['properties']);
        $this->assertSame(['id' => 4, 'year' => 2020, 'water_index' => 'MNDWI', 'source_image_date' => null], $f['properties']);
    }

    public function test_geojson_geom_null_cho_geometry_null(): void
    {
        $f = json_decode($this->show('hotspots', 'geojson', [['id' => 1, 'severity' => 2.5, 'geom' => null]])->getContent(), true)['features'][0];
        $this->assertNull($f['geometry']);
    }

    public function test_geojson_khong_escape_unicode(): void
    {
        $body = $this->show('hotspots', 'geojson', [['id' => 1, 'notes' => 'Sạt lở Sông Hậu', 'geom' => '{}']])->getContent();
        $this->assertStringContainsString('Sạt lở Sông Hậu', $body);
        $this->assertStringNotContainsString('\\u1ea1', $body);
    }

    public function test_csv_rong_chi_co_bom(): void
    {
        $this->assertSame("\xEF\xBB\xBF", $this->show('transects', 'csv')->getContent());
    }

    public function test_csv_header_dong_du_lieu_va_bool(): void
    {
        $rows = [
            ['id' => 1, 'is_significant' => true, 'geom' => 'POINT(1 2)'],
            ['id' => 2, 'is_significant' => false, 'geom' => null],
        ];
        $lines = explode("\n", trim(substr($this->show('transects', 'csv', $rows)->getContent(), 3)));
        $this->assertSame('id,is_significant,geom', $lines[0]);
        $this->assertSame('1,true,"POINT(1 2)"', $lines[1]);
        $this->assertSame('2,false,', $lines[2]);
    }

    public function test_csv_escape_dau_phay_ngoac_kep_va_xuong_dong(): void
    {
        $rows = [['id' => 1, 'notes' => "a,b \"c\"\nd", 'geom' => 'X']];
        $csv = substr($this->show('hotspots', 'csv', $rows)->getContent(), 3);
        $this->assertStringContainsString('"a,b ""c""', $csv);
    }

    public function test_csv_tieng_viet_nguyen_ven(): void
    {
        $csv = $this->show('reference', 'csv', [['id' => 1, 'location' => 'Ấp Đông Hải, Cần Thơ', 'geom' => 'X']])->getContent();
        $this->assertStringContainsString('Ấp Đông Hải, Cần Thơ', $csv);
    }

    public function test_layer_la_ngoai_bang_ten_gay_loi_undefined_key(): void
    {
        // Route đã chặn bằng whereIn; gọi thẳng controller với lớp lạ thì không có cấu hình.
        $this->expectException(\ErrorException::class);
        (new ExportController)->show('khong_co', 'csv');
    }
}
