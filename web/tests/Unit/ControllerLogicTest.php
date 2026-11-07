<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\ExportController;
use App\Http\Controllers\Api\SummaryController;
use App\Http\Controllers\MapController;
use App\Models\ShorelineChangeStat;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;

class ControllerLogicTest extends BaseUnitTest
{
    private function invoke(object $o, string $method, mixed ...$args): mixed
    {
        $m = (new ReflectionClass($o))->getMethod($method);

        return $m->invoke($o, ...$args);
    }

    private function constant(string $class, string $name): mixed
    {
        return (new ReflectionClass($class))->getConstant($name);
    }

    public static function roundValues(): array
    {
        return [
            'null' => [null, null],
            'so nguyen' => [3, 3.0],
            'lam tron len' => [1.005 + 0.001, 1.01],
            'lam tron xuong' => [-1.234, -1.23],
            'chuoi so' => ['2.456', 2.46],
            'am' => [-0.005001, -0.01],
            'zero' => [0, 0.0],
            'chuoi zero' => ['0.0', 0.0],
            'rat nho' => [0.0004, 0.0],
        ];
    }

    #[DataProvider('roundValues')]
    public function test_summary_round(mixed $in, ?float $expected): void
    {
        $r = $this->invoke(new SummaryController, 'round', $in);
        $expected === null ? $this->assertNull($r) : $this->assertEqualsWithDelta($expected, $r, 1e-9);
    }

    public function test_summary_transect_null_tra_null(): void
    {
        $this->assertNull($this->invoke(new SummaryController, 'transect', null));
    }

    public function test_summary_transect_ep_kieu(): void
    {
        $row = (new ShorelineChangeStat)->forceFill(['id' => '12', 'transect_code' => 'T012', 'lrr_m_per_year' => '-3.5']);
        $this->assertSame(
            ['id' => 12, 'transect_code' => 'T012', 'lrr_m_per_year' => -3.5],
            $this->invoke(new SummaryController, 'transect', $row)
        );
    }

    public function test_summary_bon_muc_bien_dong_theo_thu_tu(): void
    {
        $this->assertSame(['accretion', 'stable', 'mild_erosion', 'severe_erosion'], $this->constant(SummaryController::class, 'CLASSES'));
    }

    public function test_map_khoang_nam_nghien_cuu(): void
    {
        $this->assertSame([2018, 2026], $this->constant(MapController::class, 'STUDY_YEARS'));
        $this->assertSame(range(2018, 2026), range(...$this->constant(MapController::class, 'STUDY_YEARS')));
        $this->assertCount(9, range(...$this->constant(MapController::class, 'STUDY_YEARS')));
    }

    public function test_map_about_tra_view_voi_danh_sach_nam(): void
    {
        $view = (new MapController)->about();
        $this->assertSame('about.index', $view->name());
        $this->assertSame(range(2018, 2026), $view->getData()['years']);
    }

    public function test_map_admin_tra_view_admin(): void
    {
        $this->assertSame('admin.index', (new MapController)->admin()->name());
    }

    public static function layers(): array
    {
        return [['transects'], ['shorelines'], ['hotspots'], ['reference']];
    }

    #[DataProvider('layers')]
    public function test_export_layer_co_du_ba_thanh_phan_va_sql_hop_le(string $layer): void
    {
        $layers = $this->constant(ExportController::class, 'LAYERS');
        $this->assertArrayHasKey($layer, $layers);
        [$select, $alias, $order] = $layers[$layer];
        $this->assertMatchesRegularExpression('/^SELECT\s/', $select);
        $this->assertSame(1, preg_match_all('/\sFROM\s/', $select));
        $this->assertStringNotContainsString('geom', $select, 'SELECT khong duoc chua cot hinh hoc');
        $this->assertStringStartsWith($alias.'.', $order);
        $this->assertMatchesRegularExpression('/\bFROM\s+\w+\s+'.$alias.'\b/', $select);
    }

    public function test_export_layers_khop_rang_buoc_route(): void
    {
        $layers = array_keys($this->constant(ExportController::class, 'LAYERS'));
        $route = app('router')->getRoutes()->getByName('') ?? null;
        $found = null;
        foreach (app('router')->getRoutes() as $r) {
            if ($r->uri() === 'api/export/{layer}.{format}') {
                $found = $r;
            }
        }
        $this->assertNotNull($found);
        $this->assertEqualsCanonicalizing($layers, $found->wheres['layer'] === null ? [] : explode('|', $found->wheres['layer']));
        $this->assertEqualsCanonicalizing(['geojson', 'csv'], explode('|', $found->wheres['format']));
    }

    public static function csvCases(): array
    {
        return [
            'rong' => [[], ''],
            'mot dong' => [[['a' => 1, 'b' => 'x']], "a,b\n1,x\n"],
            'bool' => [[['ok' => true, 'no' => false]], "ok,no\ntrue,false\n"],
            'null' => [[['a' => null, 'b' => 2]], "a,b\n,2\n"],
            'dau phay' => [[['a' => 'x,y']], "a\n\"x,y\"\n"],
            'dau nhay' => [[['a' => 'he said "hi"']], "a\n\"he said \"\"hi\"\"\"\n"],
            'tieng viet' => [[['a' => 'Sông Hậu']], "a\n\"Sông Hậu\"\n"],
            'xuong dong' => [[['a' => "l1\nl2"]], "a\n\"l1\nl2\"\n"],
            'nhieu dong' => [[['a' => 1], ['a' => 2]], "a\n1\n2\n"],
            'backslash khong escape' => [[['a' => 'x\\"y']], "a\n\"x\\\"\"y\"\n"],
        ];
    }

    #[DataProvider('csvCases')]
    public function test_export_csv(array $rows, string $body): void
    {
        $out = $this->invoke(new ExportController, 'csv', $rows);
        $this->assertSame("\xEF\xBB\xBF".$body, $out);
    }

    public function test_export_csv_luon_bat_dau_bang_bom_utf8(): void
    {
        $this->assertStringStartsWith("\xEF\xBB\xBF", $this->invoke(new ExportController, 'csv', []));
    }
}
