<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

/**
 * Trang /gioi-thieu và /quy-trinh-qgis chỉ mô tả cách làm bằng QGIS. Mỗi số trong trang được đối chiếu với nguồn thật:
 * pipeline/data/qgis_gui/<năm>/BUOC.md, giai_doan2/BUOC.md, export/*.geojson, config.py, scenes.json, evaluation.json.
 */
class AboutStepsTest extends TestCase
{
    private const QGIS = '../pipeline/data/qgis_gui/';

    private function page(): string
    {
        // Nội dung phương pháp (/gioi-thieu) và quy trình QGIS (/quy-trinh-qgis) cùng nhau là toàn bộ phần mô tả cách làm.
        return $this->get('/gioi-thieu')->assertOk()->getContent().$this->get('/quy-trinh-qgis')->assertOk()->getContent();
    }

    /** Văn bản thuần của trang (bỏ thẻ, giải mã thực thể) để so số liệu. */
    private function text(): string
    {
        return html_entity_decode(strip_tags($this->page()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function pj(string $rel): array
    {
        return json_decode((string) file_get_contents(base_path('../pipeline/'.$rel)), true, 512, JSON_THROW_ON_ERROR);
    }

    private function buoc(string $rel): string
    {
        $path = base_path(self::QGIS.$rel);
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    private function config(string $name): float
    {
        preg_match('/^'.preg_quote($name, '/').'\s*=\s*([-0-9.]+)/m', (string) file_get_contents(base_path('../pipeline/config.py')), $m);
        $this->assertNotEmpty($m, "không đọc được $name trong config.py");

        return (float) $m[1];
    }

    /** Định dạng số kiểu Việt Nam: dấu phẩy thập phân, dấu chấm hàng nghìn, dấu trừ Unicode. */
    private function vn(float $x, int $dec = 2): string
    {
        $s = number_format(abs($x), $dec, ',', '.');

        return ($x < 0 ? "\u{2212}" : '').$s;
    }

    /** @return list<array<string,mixed>> thuộc tính transect từ export GeoJSON của QGIS */
    private function transects(): array
    {
        $g = json_decode((string) file_get_contents(base_path(self::QGIS.'giai_doan2/export/transects.geojson')), true, 512, JSON_THROW_ON_ERROR);

        return array_column($g['features'], 'properties');
    }

    // ---- Cấu trúc ----

    public function test_trang_co_chin_buoc_giai_doan_anh(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('id="giai-doan-anh"', $html);
        $this->assertSame(9, substr_count($html, 'class="step"'));
        foreach (range(1, 9) as $n) {
            $this->assertStringContainsString('id="buoc-'.$n.'"', $html);
        }
        $this->assertStringNotContainsString('id="buoc-10"', $html);
    }

    public function test_muc_luc_khop_cac_section_va_khong_con_muc_qgis_lap(): void
    {
        $html = $this->page();

        preg_match('#<nav class="doc-toc".*?</nav>#s', $html, $nav);
        preg_match_all('#href="\#([^"]+)"#', $nav[0], $links);
        $this->assertNotEmpty($links[1]);
        foreach ($links[1] as $id) {
            $this->assertSame(1, substr_count($html, 'id="'.$id.'"'), "mục lục trỏ tới #$id nhưng không có đúng một phần tử");
        }
        // mọi section của bài viết đều có trong mục lục
        preg_match_all('#<section id="([^"]+)"#', $html, $secs);
        foreach ($secs[1] as $id) {
            $this->assertContains($id, $links[1], "section #$id thiếu trong mục lục");
        }
        $this->assertStringNotContainsString('id="qgis"', $html);
        $this->assertStringNotContainsString('Thực hiện bằng QGIS', $html);
    }

    public function test_moi_buoc_co_hinh_qgis_that_dung_kich_thuoc_va_mo_ta(): void
    {
        $html = $this->page();

        preg_match_all('#<img src="(/img/[^"]+)" width="(\d+)" height="(\d+)"[^>]* alt="([^"]+)"#', $html, $m, PREG_SET_ORDER);
        $this->assertGreaterThanOrEqual(30, count($m));
        foreach ($m as [$_, $src, $w, $h, $alt]) {
            $this->assertStringStartsWith('/img/qgis/', $src, 'trang không còn dùng hình vẽ bằng mã ngoài QGIS');
            $this->assertFileExists(public_path($src));
            $size = getimagesize(public_path($src));
            $this->assertSame([(int) $w, (int) $h], [$size[0], $size[1]], "$src: width/height lệch file thật");
            $this->assertGreaterThan(20, mb_strlen($alt), "$src: alt quá ngắn");
        }
        // mỗi bước có hình chính
        $this->assertSame(9, substr_count($html, 'class="step-fig"'));
        $this->assertDirectoryDoesNotExist(public_path('img/phuong-phap'));
    }

    public function test_moi_buoc_co_phan_chi_tiet_ky_thuat_khong_can_js(): void
    {
        $html = $this->page();

        $this->assertSame(9, substr_count($html, '<details class="step-more">'));
        $this->assertSame(9, substr_count($html, '<summary>Chi tiết kỹ thuật</summary>'));
        $this->assertStringNotContainsString('<details class="step-more" open', $html);
        foreach (['Đầu vào và đầu ra', 'Thông số', 'Thuật toán', 'Kiểm tra chất lượng'] as $label) {
            $this->assertSame(9, substr_count($html, '<dt>'.$label.'</dt>'), $label);
        }
    }

    public function test_cac_buoc_dung_chu_de_va_thu_tu(): void
    {
        $html = $this->page();
        $titles = ['Nạp ảnh Sentinel-2 từ kho STAC', 'Vùng phân tích và cắt ảnh', 'Che mây và gộp composite trung vị', 'Chỉ số nước MNDWI và ngưỡng nước',
            'Mặt nạ nước, làm sạch và đường bờ', 'Baseline và transect', 'Khoảng cách và tốc độ biến động theo transect',
            'Độ bất định, phân loại và hotspot', 'Kiểm chứng, bản đồ và xuất cho web'];
        $last = 0;
        foreach ($titles as $i => $t) {
            $this->assertStringContainsString('<h3>'.$t.'</h3>', $html);
            $pos = strpos($html, 'id="buoc-'.($i + 1).'"');
            $this->assertGreaterThan($last, $pos);
            $last = $pos;
        }
    }

    // ---- Chỉ nói về QGIS ----

    public function test_chi_nhac_python_dung_mot_cho_trong_muc_han_che(): void
    {
        $html = $this->page();
        $text = $this->text();

        $this->assertSame(1, substr_count($text, 'Python'));
        preg_match('#<section id="han-che">.*?</section>#s', $html, $sec);
        $this->assertStringContainsString('chuỗi script Python được giữ làm bản đối chiếu', $sec[0]);
        $this->assertStringContainsString('không dùng cho số liệu hiển thị', $sec[0]);
        foreach (['s1_composite', 's2_water', 's3_shoreline', 's4_transects', 's5_stats', 's7_evaluate', 's9_validate', '.py', 'numpy', 'skimage', 'scipy', 'webgis:import', 'provision.sh', 'pipeline Python'] as $banned) {
            $this->assertStringNotContainsString($banned, $text, "còn dấu vết ngoài QGIS: $banned");
        }
    }

    public function test_cac_cong_cu_qgis_duoc_nhac_ten(): void
    {
        $text = $this->text();

        foreach (['Data Source Manager', 'STAC', 'Clip Raster by Mask Layer', 'GDAL Raster Calculator', 'Cell Statistics', 'Raster Calculator', 'Histogram',
            'GDAL Fill NoData', 'GDAL Sieve', 'GDAL Polygonize', 'Extract by expression', 'Buffer −50 m', 'Delete holes', 'Polygons to Lines',
            'Clip', 'Offset lines', 'Field Calculator', 'Print Layout', 'Points along geometry', 'Geometry by expression', 'Join attributes'] as $tool) {
            $this->assertStringContainsString($tool, $text, "thiếu công cụ QGIS: $tool");
        }
        $this->assertStringContainsString('QGIS 3.40 không có công cụ Otsu', $text);
        $this->assertStringContainsString('tính trên đúng histogram của ảnh MNDWI năm đó', $text);
        $this->assertStringContainsString('trong QGIS vẫn mở được histogram để đối chiếu', $text);
    }

    // ---- Số liệu khớp dữ liệu QGIS ----

    public function test_nguong_nuoc_va_do_dai_duong_bo_khop_buoc_md_tung_nam(): void
    {
        $text = $this->text();
        $thr = $len = $area = [];
        foreach (range(2018, 2026) as $y) {
            $md = $this->buoc("$y/BUOC.md");
            $this->assertSame(1, preg_match('/\|\s*Ngưỡng Otsu MNDWI\s*\|\s*([0-9,]+)/u', $md, $a), "$y: ngưỡng");
            $this->assertSame(1, preg_match('/\|\s*Độ dài đường bờ[^|]*\|\s*([0-9,]+) km/u', $md, $b), "$y: độ dài");
            $this->assertSame(1, preg_match('/\|\s*Diện tích nước liên thông lớn nhất[^|]*\|\s*([0-9,]+) km/u', $md, $c), "$y: diện tích");
            $thr[$y] = $a[1];
            $len[$y] = $b[1];
            $area[$y] = $c[1];
        }
        $t = implode('; ', array_map(fn ($y) => "$y: {$thr[$y]}", array_keys($thr)));
        $l = implode('; ', array_map(fn ($y) => "$y: {$len[$y]}", array_keys($len)));
        $this->assertStringContainsString('Ngưỡng nước MNDWI từng năm: '.$t, $text);
        $this->assertStringContainsString('Độ dài đường bờ (km): '.$l, $text);

        $f = fn (string $s) => (float) str_replace(',', '.', $s);
        $this->assertStringContainsString('từ '.number_format(min(array_map($f, $len)), 2, ',', '').' đến '.number_format(max(array_map($f, $len)), 2, ',', '').' km', $text);
        $this->assertStringContainsString(number_format(min(array_map($f, $area)), 2, ',', '').' đến '.number_format(max(array_map($f, $area)), 2, ',', '').' km²', $text);
        $this->assertStringContainsString('từ '.number_format(min(array_map($f, $thr)), 3, ',', '').' (2024) đến '.number_format(max(array_map($f, $thr)), 3, ',', '').' (2018)', $text);
    }

    public function test_so_canh_moi_nam_khop_scenes_va_buoc_md(): void
    {
        $text = $this->text();
        $scenes = $this->pj('data/composite/scenes.json');
        $used = array_map(fn ($y) => (int) $y['used'], $scenes);
        ksort($used);

        $this->assertSame(66, array_sum($used));
        $this->assertSame(126, array_sum(array_map(fn ($y) => count($y['scenes']), $scenes)));
        $this->assertStringContainsString('66 trong 126 cảnh', $text);
        $this->assertStringContainsString('Số cảnh dùng mỗi năm: '.implode('; ', array_map(fn ($y, $n) => "$y: $n", array_keys($used), $used)), $text);
        $this->assertStringContainsString('từ '.min($used).' đến '.max($used).' cảnh', $text);

        // BUOC.md của từng năm nhắc đúng số cảnh đó
        foreach ($used as $y => $n) {
            $this->assertMatchesRegularExpression('/(dùng đúng|chọn đúng|lấy đúng|chọn)\s+'.$n.'\s+cảnh/u', $this->buoc("$y/BUOC.md"), "$y: BUOC.md không nói $n cảnh");
        }
        $this->assertSame(0.30, $this->config('MAX_MASKED_AOI'));
        $this->assertStringContainsString('không quá 30%', $text);
        $this->assertStringContainsString('EPSG:32648', $text);
        $this->assertStringContainsString('2.004 × 1.331', $text);
        $this->assertStringContainsString('10 × 10 m', $text);
    }

    public function test_cong_thuc_mndwi_va_otsu_la_mathml_phan_so(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('<mi>MNDWI</mi><mo>=</mo><mfrac><mrow><mi>B03</mi><mo>−</mo><mi>B11</mi></mrow><mrow><mi>B03</mi><mo>+</mo><mi>B11</mi></mrow></mfrac>', $html);
        $this->assertStringNotContainsString('(B03 − B11) / (B03 + B11)', $this->text());
        $this->assertStringContainsString('where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0)', html_entity_decode($html));
        foreach (range(2018, 2026) as $y) {
            $this->assertStringContainsString('where(isin(B,[0,1,3,8,9,10,11]),-9999.0,A*1.0', $this->buoc("$y/BUOC.md"));
        }
    }

    public function test_so_lieu_transect_khop_export_geojson(): void
    {
        $text = $this->text();
        $rows = $this->transects();

        $this->assertCount(270, $rows);
        $classes = array_count_values(array_column($rows, 'change_class'));
        $this->assertSame(['stable' => 209, 'accretion' => 52, 'mild_erosion' => 8, 'severe_erosion' => 1], [
            'stable' => $classes['stable'], 'accretion' => $classes['accretion'], 'mild_erosion' => $classes['mild_erosion'], 'severe_erosion' => $classes['severe_erosion'],
        ]);
        $this->assertStringContainsString('Kết quả: 209 ổn định, 52 bồi, 8 lùi nhẹ và 1 lùi mạnh (T0089)', $text);
        $this->assertStringContainsString('Tổng 209 + 52 + 8 + 1 = 270', $text);
        $this->assertStringContainsString('còn 270 transect', $text);

        $lrr = array_column($rows, 'lrr');
        sort($lrr);
        $mean = array_sum($lrr) / count($lrr);
        $median = ($lrr[134] + $lrr[135]) / 2;
        $this->assertStringContainsString('LRR trung bình '.$this->vn($mean, 3).' m/năm, trung vị '.$this->vn($median, 3).' m/năm', $text);
        $this->assertStringContainsString($this->vn(min($lrr), 2).' đến +'.$this->vn(max($lrr), 2).' m/năm', $text);

        $sig = count(array_filter($rows, fn ($r) => $r['is_significant']));
        $this->assertStringContainsString("$sig transect có LRR khác 0 có ý nghĩa thống kê", $text);
        $this->assertSame(61, $sig);
        $lowR2 = array_filter($rows, fn ($r) => $r['low_r2_caution']);
        $this->assertStringContainsString(count($lowR2).' trong 270 transect có R² dưới 0,3', $text);
        $this->assertSame([], array_filter($lowR2, fn ($r) => $r['change_class'] !== 'stable'));
        $this->assertSame(array_sum(array_column($rows, 'n_despiked')), 22);
        $this->assertStringContainsString('22 quan sát', $text);

        $d = $n = 0;
        foreach ($rows as $r) {
            foreach (range(2018, 2026) as $y) {
                $d += $r["d$y"] !== null ? 1 : 0;
                $n += $r["c$y"] !== null ? 1 : 0;
            }
        }
        $this->assertStringContainsString('Tổng '.$this->vn($d, 0).' số đo, sau khử đột biến còn '.$this->vn($n, 0), $text);
        $this->assertStringContainsString('Trong '.$this->vn(270 * 9, 0).' số đo (270 × 9) chỉ '.(270 * 9 - $d).' ô trống', $text);
        $this->assertStringContainsString(count(array_filter($rows, fn ($r) => $r['n_years'] === 9)).' transect có đủ 9 năm', $text);
        $this->assertStringContainsString('chainage_m từ '.(int) min(array_column($rows, 'chainage_m')).' đến '.$this->vn(max(array_column($rows, 'chainage_m')), 0).' m', $text);
    }

    public function test_so_lieu_theo_nhom_va_hotspot_khop_giai_doan_3(): void
    {
        $text = $this->text();
        $rows = $this->transects();
        $md = $this->buoc('giai_doan2/BUOC.md');

        foreach (['stable' => 'ổn định', 'accretion' => 'bồi', 'mild_erosion' => 'lùi nhẹ', 'severe_erosion' => 'lùi mạnh'] as $k => $vi) {
            $v = array_column(array_filter($rows, fn ($r) => $r['change_class'] === $k), 'lrr');
            $this->assertNotEmpty($v);
            $mean = array_sum($v) / count($v);
            $this->assertStringContainsString("$vi ".$this->vn($mean, 3), $text, "LRR trung bình nhóm $k");
        }
        $this->assertStringContainsString('stable 209 (LRR TB 0,109', $md);
        $this->assertStringContainsString('TB 0,389 m/năm, trung vị 0,269', $md);

        $hs = json_decode((string) file_get_contents(base_path(self::QGIS.'giai_doan2/export/hotspots.geojson')), true, 512, JSON_THROW_ON_ERROR)['features'];
        $this->assertCount(1, $hs);
        $this->assertSame(2.83, round($hs[0]['properties']['severity'], 2));
        $this->assertStringContainsString('mức lùi 2,83 m/năm giai đoạn '.$hs[0]['properties']['period_start_year'].'–'.$hs[0]['properties']['period_end_year'], $text);
        $this->assertStringContainsString('T0089', $hs[0]['properties']['notes']);

        $t = current(array_filter($rows, fn ($r) => $r['transect_code'] === 'T0089'));
        $this->assertSame('severe_erosion', $t['change_class']);
        $this->assertStringContainsString('NSM '.$this->vn($t['nsm']).' m, EPR '.$this->vn($t['epr'], 3).' m/năm, LRR '.$this->vn($t['lrr'], 3).' m/năm, SE '.$this->vn($t['se_lrr'], 3).', t = '.$this->vn($t['t_crit'], 3).', R² = '.$this->vn($t['r2'], 3), $text);
        $this->assertStringContainsString('khoảng cách giảm từ '.$this->vn($t['d2018']).' m (2018) xuống '.$this->vn($t['d2026']).' m (2026)', $text);
        $this->assertStringContainsString('±'.$this->vn($t['unc_lrr'], 3).' m/năm, độ bất định EPR '.$this->vn($t['unc_epr'], 3).' m/năm', $text);
        $this->assertStringContainsString('|EPR| = '.$this->vn(abs($t['epr'])).' m/năm nhỏ hơn độ bất định EPR '.$this->vn($t['unc_epr']).' m/năm', $text);
        $this->assertLessThan($t['unc_epr'], abs($t['epr']));
        $this->assertStringContainsString('chainage 4.425 m', $text);
        $this->assertSame(4425.0, (float) $t['chainage_m']);
    }

    public function test_tham_so_phan_loai_va_do_bat_dinh_khop_config(): void
    {
        $text = $this->text();

        $this->assertSame(-2.0, $this->config('SEVERE_LRR'));
        $this->assertStringContainsString('Ngưỡng lùi mạnh LRR ≤ −2,0 m/năm', $text);
        $this->assertSame(3.0, $this->config('MIN_CONSECUTIVE_DECLINE'));
        $this->assertStringContainsString('Số mốc năm liên tiếp tối thiểu 3', $text);
        $this->assertStringContainsString('Cờ R² dưới '.$this->vn($this->config('R2_CAUTION'), 1), $text);
        $this->assertStringContainsString('khử đột biến '.(int) $this->config('DESPIKE_M').' m', $text);
        $this->assertStringContainsString('Ngưỡng khử đột biến '.(int) $this->config('DESPIKE_M').' m', $text);
        $this->assertStringContainsString('cách nhau 50 m', $text);
        $this->assertSame(50.0, $this->config('TRANSECT_SPACING'));
        $this->assertStringContainsString('dài 600 m', $text);
        $this->assertSame(600.0, $this->config('TRANSECT_LENGTH'));
        $this->assertStringContainsString('Offset lines +250 m', $text);
        $this->assertSame(250.0, $this->config('BASELINE_OFFSET'));
        $this->assertStringContainsString('Z95 = '.str_replace('.', ',', rtrim(number_format($this->config('Z95'), 6, '.', ''), '0')), str_replace('<sub>', '', str_replace('</sub>', '', $text)));
        $this->assertStringContainsString('Bán kính khớp sự kiện sạt lở '.(int) $this->config('MATCH_RADIUS').' m', $text);
        $this->assertStringContainsString('vùng đệm thống kê '.(int) $this->config('ZONE_BUFFER').' m', $text);

        // công thức giai đoạn 3: u_shoreline = sqrt(5² + 6² + U_classify²), năm khác 21,7
        $up = $this->config('U_PIXEL');
        $ug = $this->config('U_GEOREF_95') / $this->config('GEOREF_SIGMA_K');
        $this->assertSame([5.0, 6.0], [$up, $ug]);
        $this->assertStringContainsString('= √(5² + 6² + U', $text);
        $this->assertSame(['2018' => 16.73, '2022' => 17.82, '2026' => 21.69], array_map('floatval', $this->pj('output/evaluation.json')['u_classify_suggestion']));
        $this->assertStringContainsString('16,73 (2018), 17,82 (2022), 21,69 (2026), các năm khác 21,7', $text);
        $this->assertSame(21.7, $this->config('U_CLASSIFY_DEFAULT'));
        $u = fn (float $c) => sqrt($up ** 2 + $ug ** 2 + $c ** 2);
        $this->assertStringContainsString($this->vn(round($u(16.73), 1), 1).' đến '.$this->vn(round($u(21.7), 1), 1).' m', $text);
        $this->assertStringContainsString('18,5 đến 23,1 m', $text);
        $this->assertStringContainsString('U<sub>classify</sub> = 21,7 m', $this->page());
        $buoc = $this->buoc('giai_doan2/BUOC.md');
        $this->assertStringContainsString("map('2018',16.73,'2022',17.82,'2026',21.69)", $buoc);
        $this->assertStringContainsString('21.7', $buoc);
        $this->assertStringContainsString('U<sub>pixel</sub> = 5 m', $this->page());
        $this->assertStringContainsString('12 m ở độ tin cậy 95,5% (2σ) nên dùng 6 m (1σ)', $text);
    }

    public function test_so_lieu_kiem_chung_khop_evaluation_va_buoc_md(): void
    {
        $text = $this->text();
        $e = $this->pj('output/evaluation.json');
        $md = $this->buoc('giai_doan2/BUOC.md');

        $f1 = $e['mean_f1'];
        $r = $e['mean_rmse_m'];
        $this->assertStringContainsString('NDWI '.$this->vn($f1['ndwi'], 4).'; MNDWI '.$this->vn($f1['mndwi'], 4).'; AWEI '.$this->vn($f1['awei'], 4), $text);
        $this->assertStringContainsString('NDWI '.$this->vn($r['ndwi']).' m; MNDWI '.$this->vn($r['mndwi']).' m; AWEI '.$this->vn($r['awei']).' m', $text);
        $this->assertSame('mndwi', $e['selected_index']);
        $this->assertSame('awei', $e['best_index_by_mean_f1']);
        $this->assertStringContainsString($this->vn($f1['awei'], 4).' so với '.$this->vn($f1['mndwi'], 4), $text);
        $this->assertStringContainsString('hoà nhờ ngưỡng '.$this->vn($this->config('SELECT_TIE_F1'), 3), $text);
        foreach ($e['by_year'] as $year => $d) {
            $this->assertCount(5, $d['windows']);
            $this->assertSame(59, $d['main_shoreline_error']['kept']['n_transects']);
        }
        $this->assertStringContainsString('59 transect mỗi năm', $text);
        $m = array_map(fn ($y) => $e['by_year'][$y]['main_shoreline_error']['kept']['rmse_m'], ['2018', '2022', '2026']);
        $this->assertStringContainsString('RMSE '.$this->vn($m[0]).' m (2018), '.$this->vn($m[1]).' m (2022), '.$this->vn($m[2]).' m (2026)', $text);

        // so sánh với bản tính trước: số trong BUOC.md giai đoạn 2
        $this->assertStringContainsString('**RMSE 1,389**', $md);
        $this->assertStringContainsString('164/261 (62,8 %)', $md);
        $this->assertStringContainsString('chênh LRR có RMSE 1,389 m/năm', $text);
        $this->assertStringContainsString('164 trong 261 ghép được', $text);
        $this->assertStringContainsString('62,8%', $text);
        $this->assertStringContainsString('stable 181, accretion 71, mild 10, severe 4', $md);
        $this->assertStringContainsString('266 transect và 4 transect lùi mạnh', $text);
        $this->assertStringContainsString('max |Δ| = 8,6', $md);
        $this->assertStringContainsString('lớn nhất 8,6 m/năm', $text);
    }

    public function test_doi_chieu_sat_lo_khop_vung_canh_bao(): void
    {
        $text = $this->text();
        $ref = $this->pj('output/reference.json');
        $this->assertCount(2, $ref['features']);
        $this->assertSame(3377, $this->pj('output/validation.json')['events'][0]['zone_length_m']);

        $this->assertStringContainsString('Vùng cảnh báo sạt lở dài 3.377 m', $text);
        $this->assertStringContainsString('0/2 sự kiện khớp', $text);
        $this->assertStringContainsString('hotspot duy nhất (T0089) cách vùng cảnh báo 5.183 m', $text);
        $this->assertStringContainsString('70 transect trong vùng đệm 100 m (49 ổn định, 21 bồi)', $text);
        $this->assertStringContainsString('hotspot duy nhất (T0089) cách vùng cảnh báo khoảng 5,2 km', $text);
        // số liệu ở mục Sạt lở tham chiếu nhất quán với bước 9
        $this->assertStringContainsString('có 70 transect, trong đó 21 bồi và 49 ổn định', $text);
        $this->assertStringContainsString('2019', $text);
        $this->assertStringContainsString('2020', $text);
    }

    public function test_hop_han_che_ghi_ro_cho_khong_khop(): void
    {
        $html = $this->page();

        $this->assertStringContainsString('id="han-che-giai-doan"', $html);
        preg_match('#<aside class="step-limits".*?</aside>#s', $html, $box);
        $this->assertNotEmpty($box);
        $this->assertGreaterThanOrEqual(8, substr_count($box[0], '<li>'));
        $text = html_entity_decode(strip_tags($box[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('không khớp (0/2 sự kiện', $text);
        $this->assertStringContainsString('QGIS 3.40 không có công cụ Otsu', $text);
        $this->assertStringNotContainsString('Python', $text);
    }

    public function test_dau_tru_dung_unicode_trong_cac_buoc(): void
    {
        $html = $this->page();

        $this->assertStringContainsString("LRR \u{2212}2,827 m/năm", $html);
        $this->assertStringNotContainsString('LRR -2,827', $html);
        $this->assertDoesNotMatchRegularExpression('/\s-\d,\d+ m\/năm/u', $this->text());
    }

    public function test_hinh_xoa_khong_con_tham_chieu_va_hinh_dung_ton_tai(): void
    {
        $html = $this->page();

        foreach (['/img/phuong-phap/', 'p1-loc-canh', 'doi_chieu_2021', 'phan_loai.webp'] as $gone) {
            $this->assertStringNotContainsString($gone, $html);
        }
        preg_match_all('#/img/qgis/[a-z0-9-]+\.webp#', $html, $m);
        $this->assertGreaterThanOrEqual(30, count(array_unique($m[0])));
        foreach (array_unique($m[0]) as $src) {
            $this->assertFileExists(public_path($src));
        }
        // 3 bản đồ Print Layout có mặt
        foreach (['c1-ban-do-9-duong-bo', 'c2-ban-do-transect-lrr', 'c3-ban-do-so-sanh-lrr'] as $map) {
            $this->assertStringContainsString("/img/qgis/$map.webp", $html);
        }
    }
}
