<?php

namespace Tests\Unit;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;

/** Phần tính toán của webgis:compare-reference, với DB::connection('pgsql')->select() được mock. */
class CompareReferenceEventsExtraTest extends BaseUnitTest
{
    private function row(int $id, ?float $nearest, ?string $date = '2019-05-01'): object
    {
        return (object) ['id' => $id, 'event_date' => $date, 'description' => 'd', 'nearest_m' => $nearest];
    }

    /** @return array{int, string} [exit code, output] */
    private function run_(array $rows, array $args = []): array
    {
        $conn = Mockery::mock(Connection::class);
        $conn->shouldReceive('select')->once()->andReturn($rows);
        DB::shouldReceive('connection')->with('pgsql')->once()->andReturn($conn);
        $out = new BufferedOutput;
        $code = Artisan::call('webgis:compare-reference', $args, $out);

        return [$code, $out->fetch()];
    }

    public function test_khong_co_su_kien_canh_bao_va_thanh_cong(): void
    {
        [$code, $out] = $this->run_([]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('Không có sự kiện tham chiếu.', $out);
        $this->assertStringNotContainsString('sự kiện có hotspot', $out);
    }

    public function test_tat_ca_trung_khop(): void
    {
        [, $out] = $this->run_([$this->row(1, 10.0), $this->row(2, 499.9)]);
        $this->assertStringContainsString('2/2 sự kiện có hotspot trong bán kính 500 m.', $out);
        $this->assertSame(2, substr_count($out, '✓ trùng khớp'));
    }

    public function test_khong_cai_nao_trung_khop(): void
    {
        [, $out] = $this->run_([$this->row(1, 501.0), $this->row(2, 9000.0)]);
        $this->assertStringContainsString('0/2 sự kiện', $out);
        $this->assertStringNotContainsString('✓', $out);
    }

    public static function boundaries(): array
    {
        return [
            'dung bang buffer tinh la trung' => [500.0, '500', 1],
            'vua qua buffer' => [500.01, '500', 0],
            'zero luon trung' => [0.0, '500', 1],
            'buffer 0 va khoang cach 0' => [0.0, '0', 1],
            'buffer 0 va khoang cach 0.1' => [0.1, '0', 0],
            'buffer thap phan' => [12.5, '12.5', 1],
            'buffer am khong ai trung' => [0.0, '-1', 0],
            'buffer rat lon' => [1e7, '100000000', 1],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_bien_so_sanh_nho_hon_hoac_bang(float $nearest, string $buffer, int $expectedHits): void
    {
        [, $out] = $this->run_([$this->row(1, $nearest)], ['--buffer' => $buffer]);
        $this->assertStringContainsString("$expectedHits/1 sự kiện", $out);
    }

    public function test_nearest_null_la_khong_co_hotspot_va_khong_trung(): void
    {
        [, $out] = $this->run_([$this->row(7, null)]);
        $this->assertStringContainsString('hotspot gần nhất: không có', $out);
        $this->assertStringContainsString('0/1', $out);
        $this->assertStringContainsString('✗', $out);
    }

    public function test_event_date_null_hien_gach_ngang(): void
    {
        [, $out] = $this->run_([$this->row(3, 5.0, null)]);
        $this->assertStringContainsString('#3 —', $out);
    }

    public function test_khoang_cach_duoc_lam_tron_met(): void
    {
        [, $out] = $this->run_([$this->row(1, 123.5), $this->row(2, 123.4)]);
        $this->assertStringContainsString('124 m', $out);
        $this->assertStringContainsString('123 m', $out);
    }

    public function test_buffer_khong_phai_so_tro_thanh_0(): void
    {
        [, $out] = $this->run_([$this->row(1, 0.0), $this->row(2, 1.0)], ['--buffer' => 'abc']);
        $this->assertStringContainsString('1/2 sự kiện có hotspot trong bán kính 0 m.', $out);
    }

    public function test_buffer_thap_phan_bi_cat_khi_in_nhung_van_so_sanh_chinh_xac(): void
    {
        // sprintf('%d') cắt 500.9 -> 500 trong câu tổng kết, nhưng phép so sánh dùng 500.9
        [, $out] = $this->run_([$this->row(1, 500.5)], ['--buffer' => '500.9']);
        $this->assertStringContainsString('1/1 sự kiện có hotspot trong bán kính 500 m.', $out);
    }

    public function test_mot_dong_moi_su_kien_va_thu_tu_giu_nguyen(): void
    {
        [, $out] = $this->run_([$this->row(30, 1.0), $this->row(10, 2.0), $this->row(20, 3.0)]);
        $this->assertLessThan(strpos($out, '#10'), strpos($out, '#30'));
        $this->assertLessThan(strpos($out, '#20'), strpos($out, '#10'));
    }

    public function test_truy_van_nhom_theo_su_kien_va_dung_geography(): void
    {
        $sql = null;
        $conn = Mockery::mock(Connection::class);
        $conn->shouldReceive('select')->once()->with(Mockery::on(function ($q) use (&$sql) {
            $sql = $q;

            return true;
        }))->andReturn([]);
        DB::shouldReceive('connection')->with('pgsql')->andReturn($conn);
        Artisan::call('webgis:compare-reference');
        $this->assertStringContainsString('::geography', $sql);
        $this->assertStringContainsString('LEFT JOIN erosion_hotspots h ON TRUE', $sql);
        $this->assertStringContainsString('GROUP BY e.id, e.event_date, e.description', $sql);
        $this->assertStringContainsString('ORDER BY e.event_date', $sql);
    }

    public function test_so_luong_lon_tinh_dung(): void
    {
        $rows = [];
        for ($i = 1; $i <= 200; $i++) {
            $rows[] = $this->row($i, $i % 4 === 0 ? 900.0 : 100.0);
        }
        [, $out] = $this->run_($rows);
        $this->assertStringContainsString('150/200 sự kiện', $out);
    }
}
