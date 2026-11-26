<?php

namespace Tests\Feature\Pages;

use Tests\TestCase;

class AboutStepsTest extends TestCase
{
    public function test_trang_gioi_thieu_co_muoi_mot_buoc_giai_doan_anh(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString('id="giai-doan-anh"', $html);
        $this->assertSame(11, substr_count($html, 'class="step"'));
        foreach (range(1, 11) as $n) {
            $this->assertStringContainsString('id="buoc-'.$n.'"', $html);
        }
    }

    public function test_moi_buoc_co_hinh_that_va_mo_ta_thay_the(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        preg_match_all('#<img src="(/img/phuong-phap/[^"]+)"[^>]* alt="([^"]+)"#', $html, $m);
        $this->assertGreaterThanOrEqual(11, count($m[1]));
        foreach ($m[1] as $i => $src) {
            $this->assertFileExists(public_path($src));
            $this->assertNotSame('', trim($m[2][$i]));
        }
    }

    public function test_so_lieu_khop_voi_dau_ra_pipeline(): void
    {
        $summary = json_decode((string) file_get_contents(base_path('../pipeline/output/summary.json')), true);
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString((string) $summary['transects'].' transect', $html);
        $c = $summary['classes'];
        $this->assertStringContainsString("{$c['accretion']} bồi, {$c['stable']} ổn định, {$c['mild_erosion']} lùi nhẹ, {$c['severe_erosion']} lùi mạnh", $html);
    }

    public function test_dau_tru_dung_unicode_trong_cac_buoc(): void
    {
        $html = $this->get('/gioi-thieu')->assertOk()->getContent();

        $this->assertStringContainsString("\u{2212}2,67 m/năm", $html);
    }

    // ---- Bản mở rộng: chi tiết kỹ thuật, hình bổ sung, hộp hạn chế, số liệu khớp pipeline ----

    private function page(): string
    {
        return $this->get('/gioi-thieu')->assertOk()->getContent();
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

    public function test_moi_buoc_co_phan_chi_tiet_ky_thuat_bam_de_xem_khong_can_js(): void
    {
        $html = $this->page();

        $this->assertSame(11, substr_count($html, '<details class="step-more">'));
        $this->assertSame(11, substr_count($html, '<summary>Chi tiết kỹ thuật</summary>'));
        // đóng sẵn (không có thuộc tính open) và không ẩn bằng hidden/display: nội dung có trong HTML thuần
        $this->assertStringNotContainsString('<details class="step-more" open', $html);
        foreach (['Đầu vào và đầu ra', 'Thông số', 'Công thức, thuật toán', 'Kiểm tra chất lượng'] as $label) {
            $this->assertSame(11, substr_count($html, '<dt>'.$label.'</dt>'), $label);
        }
        $this->assertStringNotContainsString('role="button"', $html);
    }

    public function test_cac_buoc_dau_va_cuoi_moi_them_dung_chu_de(): void
    {
        $html = $this->page();

        foreach (['Thu thập và lọc cảnh', 'Tiền xử lý: cắt vùng, hiệu chỉnh, composite', 'Độ chính xác và độ bất định', 'Xuất ra GeoServer và web'] as $t) {
            $this->assertStringContainsString('<h3>'.$t.'</h3>', $html);
        }
        $this->assertLessThan(strpos($html, 'id="buoc-2"'), strpos($html, 'Thu thập và lọc cảnh'));
        $this->assertLessThan(strpos($html, 'id="buoc-11"'), strpos($html, 'id="buoc-10"'));
    }

    public function test_hinh_phu_ton_tai_dung_kich_thuoc_va_co_mo_ta(): void
    {
        $html = $this->page();

        preg_match_all('#<img src="(/img/phuong-phap/[^"]+)" width="(\d+)" height="(\d+)" alt="([^"]+)"#', $html, $m, PREG_SET_ORDER);
        $files = array_column($m, 1);
        foreach (['p1-loc-canh', 'p2-bang-pho', 'p3-cat-vung', 'p4-otsu', 'p5-truoc-sau', 'p6-phong-to-transect', 'p7-do-chinh-xac', 'p8-bat-dinh', 'p9-xuat-geoserver'] as $name) {
            $this->assertContains("/img/phuong-phap/$name.webp", $files);
        }
        foreach ($m as [$_, $src, $w, $h, $alt]) {
            $size = getimagesize(public_path($src));
            $this->assertSame([(int) $w, (int) $h], [$size[0], $size[1]], "$src: width/height lệch file thật");
            $this->assertGreaterThan(20, mb_strlen($alt), "$src: alt quá ngắn");
        }
    }

    public function test_hop_han_che_ghi_ro_cho_khong_khop(): void
    {
        $html = $this->page();
        $v = $this->pj('output/validation.json');
        $e = $this->pj('output/evaluation.json');

        $this->assertStringContainsString('id="han-che-giai-doan"', $html);
        preg_match('#<aside class="step-limits".*?</aside>#s', $html, $box);
        $this->assertNotEmpty($box);
        $this->assertGreaterThanOrEqual(8, substr_count($box[0], '<li>'));
        $text = html_entity_decode(strip_tags($box[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString("{$v['matched']}/{$v['total']} sự kiện", $text);
        $this->assertStringContainsString($this->vn($v['events'][0]['nearest_hotspot_m'], 0).' m', $text);
        // AWEI nhỉnh hơn MNDWI về F1 mà không được chọn: phải nói thẳng
        $this->assertSame('awei', $e['best_index_by_mean_f1']);
        $this->assertNotSame('awei', $e['selected_index']);
        $this->assertStringContainsString($this->vn($e['mean_f1']['awei'], 4).' so với '.$this->vn($e['mean_f1']['mndwi'], 4), $text);
    }

    public function test_so_lieu_buoc_1_2_khop_scenes_va_config(): void
    {
        $text = $this->text();
        $scenes = $this->pj('data/composite/scenes.json');

        $total = array_sum(array_map(fn ($y) => count($y['scenes']), $scenes));
        $used = array_sum(array_column($scenes, 'used'));
        $this->assertStringContainsString("qua bước lọc sơ này còn $total cảnh", $text);
        $this->assertStringContainsString("Còn lại $used cảnh được dùng", $text);
        $this->assertStringContainsString("$used trên $total cảnh được dùng", $text);
        $usedBy = array_map(fn ($y) => $y['used'], $scenes);
        $this->assertStringContainsString('từ '.min($usedBy).' cảnh ('.array_search(min($usedBy), $usedBy).')', $text);
        $this->assertStringContainsString('đến '.max($usedBy).' cảnh ('.array_search(max($usedBy), $usedBy).')', $text);
        $this->assertStringContainsString('đến '.max(array_column($scenes, 'used')).' cảnh', $text);
        $counts = array_map(fn ($y) => count($y['scenes']), $scenes);
        $this->assertStringContainsString('từ '.min($counts).' đến '.max($counts).' cảnh', $text);

        $maxUsed = max(array_map(fn ($y) => max(array_column(array_filter($y['scenes'], fn ($s) => $s['used']), 'masked_fraction')), $scenes));
        $this->assertStringContainsString('cao nhất là '.$this->vn($maxUsed, 3).' (2025)', $text);
        $this->assertLessThanOrEqual($this->config('MAX_MASKED_AOI'), $maxUsed);

        $this->assertStringContainsString('&gt; '.$this->vn($this->config('MAX_MASKED_AOI'), 2), $this->page());
        $this->assertStringContainsString('cảnh che hơn '.(int) ($this->config('MAX_MASKED_AOI') * 100).'%', $text);
        $this->assertStringContainsString('mây metadata từ '.(int) $this->config('PREFILTER_CLOUD').'%', $text);
        $this->assertStringContainsString('Offset hiệu chỉnh: 1000', $text);
        $this->assertStringContainsString(number_format($this->config('RESOLUTION'), 0).' m', $text);
        $this->assertStringContainsString('EPSG:32648', $text);
    }

    public function test_so_lieu_buoc_3_4_khop_nguong_otsu_va_lam_sach(): void
    {
        $text = $this->text();
        $f = $this->pj('output/web_steps.json');

        $this->assertStringContainsString('ngưỡng Otsu năm này là '.$this->vn($f['otsu']['threshold'], 3), $text);
        $this->assertStringContainsString('1.347.886 pixel hợp lệ, trong đó '.$this->vn($f['otsu']['water_share'] * 100, 2).'% là nước', $text);
        $this->assertSame(1347886, $f['otsu']['pixels_in_zone']);

        $c = $f['clean'];
        $this->assertSame($c['water_before_px'] - $c['removed_px'] + $c['added_px'], $c['water_after_px']);
        $this->assertStringContainsString('bỏ '.$this->vn($c['removed_px'], 0).' pixel nước (khoảng '.(int) round($c['removed_ha']).' ha)', $text);
        $this->assertStringContainsString('trước làm sạch '.$this->vn($c['water_before_px'], 0).' pixel nước, sau làm sạch '.$this->vn($c['water_after_px'], 0).' pixel', $text);
        $this->assertStringContainsString('(519,2 ha), thêm 0 pixel', $text);
        $this->assertSame(0, $c['added_px']);

        // thông số hình thái học khớp hằng số trong s3_shoreline.py
        $s3 = (string) file_get_contents(base_path('../pipeline/s3_shoreline.py'));
        $this->assertMatchesRegularExpression('/^OPEN_RADIUS = 5\b/m', $s3);
        $this->assertMatchesRegularExpression('/^MIN_HOLE = 50_000 \/ 100\b/m', $s3);
        $this->assertMatchesRegularExpression('/^MIN_GAP = 25\b/m', $s3);
        $this->assertMatchesRegularExpression('/^MIN_PIECE = 200\b/m', $s3);
        $this->assertMatchesRegularExpression('/^BANK_ZONE = 100\b/m', $s3);
        $this->assertStringContainsString('bán kính 5 pixel (50 m)', $text);
        $this->assertStringContainsString('Lấp lỗ nhỏ hơn 500 pixel (5 ha)', $text);
        $this->assertStringContainsString('Bỏ mảnh ngắn hơn 200 m', $text);
        $this->assertStringContainsString('nới 100 m', $text);
        $this->assertStringContainsString('Đơn giản hoá: '.(int) $this->config('SMOOTH_TOLERANCE').' m', $text);
    }

    public function test_ba_chi_so_nuoc_dung_cong_thuc_trong_s2_water(): void
    {
        $text = $this->text();
        $s2 = (string) file_get_contents(base_path('../pipeline/s2_water.py'));

        $this->assertStringContainsString('(g - nir) / (g + nir)', $s2);
        $this->assertStringContainsString('(g - sw1) / (g + sw1)', $s2);
        $this->assertStringContainsString('4 * (g - sw1) - (0.25 * nir + 2.75 * sw2)', $s2);
        $this->assertStringContainsString('NDWI = (B03 − B08) / (B03 + B08)', $text);
        $this->assertStringContainsString('MNDWI = (B03 − B11) / (B03 + B11)', $text);
        $this->assertStringContainsString('4(B03 − B11) − (0,25·B08 + 2,75·B12)', $text);
        $this->assertStringContainsString('ANALYSIS_BUFFER = 1000', $s2);
        $this->assertStringContainsString('đệm 1.000 m', $text);
    }

    public function test_so_lieu_buoc_5_6_khop_duong_bo_baseline_transect(): void
    {
        $text = $this->text();
        $summary = $this->pj('output/summary.json');

        // chiều dài đường bờ từng năm đọc từ data/shoreline/*.json (EPSG:32648, mét)
        $len = [];
        foreach (range(2018, 2026) as $y) {
            $g = $this->pj("data/shoreline/$y.json")['features'][0]['geometry'];
            $parts = $g['type'] === 'MultiLineString' ? $g['coordinates'] : [$g['coordinates']];
            $this->assertCount(1, $parts, "đường bờ $y không còn là một đoạn");
            $len[$y] = $this->lineLength($parts[0]);
        }
        $this->assertStringContainsString('dài '.$this->vn(min($len) / 1000).' đến '.$this->vn(max($len) / 1000).' km', $text);
        $this->assertStringContainsString('(2018: '.$this->vn($len[2018] / 1000).' km; 2026: '.$this->vn($len[2026] / 1000).' km)', $text);

        // baseline + số transect sinh ra / bị bỏ
        $base = $this->pj('data/baseline.json')['features'][0]['geometry']['coordinates'];
        $L = $this->lineLength($base);
        $this->assertStringContainsString('dài '.$this->vn($L / 1000).' km', $text);
        $sinh = (int) ceil(($L - $this->config('TRANSECT_SPACING') / 2) / $this->config('TRANSECT_SPACING'));
        $this->assertStringContainsString("Sinh $sinh vị trí", $text);
        $this->assertStringContainsString('bỏ '.($sinh - $summary['transects']).' transect cắt nhau, còn '.$summary['transects'], $text);
        $this->assertStringContainsString('Khoảng cách transect '.(int) $this->config('TRANSECT_SPACING').' m, chiều dài '.(int) $this->config('TRANSECT_LENGTH').' m, baseline cách đường bờ '.(int) $this->config('BASELINE_OFFSET').' m', $text);

        // đủ 9 năm lúc đo: tổng quan sát sau khử đột biến + số bị loại = 267 × 9
        $obs = 0;
        $tr = $this->pj('output/transects.json')['features'];
        foreach ($tr as $f) {
            $obs += count(array_filter($f['properties']['distances'], fn ($d) => $d !== null));
        }
        $this->assertSame($summary['transects'] * 9, $obs + $summary['despiked_observations']);
        $this->assertStringContainsString($this->vn($obs, 0).' trên '.$this->vn($summary['transects'] * 9, 0).' quan sát', $text);
        $this->assertStringContainsString($this->vn($summary['transects'] * 9, 0).' quan sát', $text);
        $this->assertStringContainsString('17 transect cắt', $text);
        $this->assertSame(17, $this->pj('output/web_steps.json')['zoom']['transects']);
    }

    public function test_so_lieu_buoc_7_8_khop_thong_ke_transect(): void
    {
        $text = $this->text();
        $summary = $this->pj('output/summary.json');
        $rows = array_column($this->pj('output/transects.json')['features'], 'properties');

        $t = current(array_filter($rows, fn ($r) => $r['transect_code'] === 'T0094'));
        $s = $t['stats'];
        $this->assertStringContainsString('NSM '.$this->vn($s['nsm_m']).' m, EPR '.$this->vn($s['epr_m_per_year'], 3).' m/năm, LRR '.$this->vn($s['lrr_m_per_year'], 3).' m/năm, SE '.str_replace('.', ',', (string) $s['se_lrr']).', t = '.$this->vn($s['t_crit'], 3).', R² = '.$this->vn($s['r_squared'], 3), $text);
        $this->assertStringContainsString($s['consecutive_erosion_periods'].' giai đoạn giảm liên tục', $text);
        $this->assertStringContainsString('±'.$this->vn($s['unc_lrr']).' m', $text);
        $this->assertStringContainsString('|EPR| = '.$this->vn(abs($s['epr_m_per_year'])).' m/năm nhỏ hơn độ bất định EPR '.$this->vn($s['unc_epr']).' m/năm', $text);
        $this->assertLessThan($s['unc_epr'], abs($s['epr_m_per_year']));

        $stats = array_column($rows, 'stats');
        $sig = count(array_filter($stats, fn ($x) => $x['is_significant']));
        $lowR2 = count(array_filter($stats, fn ($x) => $x['low_r2_caution']));
        $this->assertStringContainsString("$sig có LRR khác 0 có ý nghĩa thống kê; $lowR2 transect có R² dưới 0,3", $text);
        $this->assertStringContainsString("$lowR2 trong {$summary['transects']} transect có R² dưới 0,3", $text);
        // 132 transect cờ R² thấp đều là nhóm ổn định, như hộp hạn chế khẳng định
        $this->assertSame([], array_filter($stats, fn ($x) => $x['low_r2_caution'] && $x['change_class'] !== 'stable'));

        $c = $summary['classes'];
        $this->assertSame($summary['transects'], array_sum($c));
        $this->assertStringContainsString("Tổng {$c['accretion']} + {$c['stable']} + {$c['mild_erosion']} + {$c['severe_erosion']} = {$summary['transects']}", $text);
        $this->assertStringContainsString('('.$this->vn($summary['M_lrr_erosion']).' và '.$this->vn($summary['S_lrr_erosion']).' m/năm)', $text);
        $this->assertStringContainsString($summary['despiked_observations'].' quan sát', $text);
        $this->assertStringContainsString('Ngưỡng lùi mạnh LRR ≤ '.$this->vn($this->config('SEVERE_LRR'), 1).' m/năm', $text);
        $this->assertStringContainsString('Số mốc năm liên tiếp tối thiểu '.(int) $this->config('MIN_CONSECUTIVE_DECLINE'), $text);
        $this->assertStringContainsString('Cờ R² dưới '.$this->vn($this->config('R2_CAUTION'), 1), $text);
        $this->assertStringContainsString('Ngưỡng khử đột biến '.(int) $this->config('DESPIKE_M').' m', $text);
        $this->assertStringContainsString('Z95 = '.str_replace('.', ',', rtrim(number_format($this->config('Z95'), 6, '.', ''), '0')), $text);
    }

    public function test_so_lieu_buoc_9_khop_evaluation_va_do_bat_dinh(): void
    {
        $text = $this->text();
        $e = $this->pj('output/evaluation.json');
        $summary = $this->pj('output/summary.json');
        $cc = $this->pj('output/crosscheck.json');

        $f1 = $e['mean_f1'];
        $r = $e['mean_rmse_m'];
        $this->assertStringContainsString('NDWI '.$this->vn($f1['ndwi'], 4).'; MNDWI '.$this->vn($f1['mndwi'], 4).'; AWEI '.$this->vn($f1['awei'], 4), $text);
        $this->assertStringContainsString('NDWI '.$this->vn($r['ndwi']).' m; MNDWI '.$this->vn($r['mndwi']).' m; AWEI '.$this->vn($r['awei']).' m', $text);
        $this->assertSame('mndwi', $e['selected_index']);
        $this->assertStringContainsString('Ngưỡng hoà F1 '.$this->vn($this->config('SELECT_TIE_F1'), 3), $text);

        $by = $e['by_year'];
        $this->assertCount(3, $by);
        foreach ($by as $year => $d) {
            $this->assertCount(5, $d['windows']);
            $this->assertSame(18000, $d['mndwi']['pixels']);
        }
        $this->assertStringContainsString('RMSE đường bờ là '.$this->vn($by['2018']['shoreline_error']['mndwi']['rmse_m'], 1).' m (2018), '.$this->vn($by['2022']['shoreline_error']['mndwi']['rmse_m']).' m (2022), '.$this->vn($by['2026']['shoreline_error']['mndwi']['rmse_m']).' m (2026)', $text);
        $this->assertStringContainsString('59 mỗi năm (2018, 2022, 2026)', $text);
        $this->assertSame([59, 59, 59], [$by['2018']['shoreline_error']['mndwi']['n_transects'], $by['2022']['shoreline_error']['mndwi']['n_transects'], $by['2026']['shoreline_error']['mndwi']['n_transects']]);

        // U_shoreline = sqrt(U_pixel² + U_georef² + U_classify²) tính lại từ config + evaluation
        $up = $this->config('U_PIXEL');
        $ug = $this->config('U_GEOREF_95') / $this->config('GEOREF_SIGMA_K');
        $this->assertStringContainsString('U<sub>pixel</sub> = '.(int) $up.' m', $this->page());
        $this->assertStringContainsString('12 m / 2 = '.(int) $ug.' m', $text);
        foreach ($summary['u_shoreline_by_year'] as $year => $u) {
            $cls = $e['u_classify_suggestion'][$year] ?? $this->config('U_CLASSIFY_DEFAULT');
            $this->assertEqualsWithDelta(sqrt($up ** 2 + $ug ** 2 + $cls ** 2), $u, 0.01, "U_shoreline $year");
        }
        $u = $summary['u_shoreline_by_year'];
        $this->assertStringContainsString($this->vn($u['2018']).' m (2018), '.$this->vn($u['2022']).' m (2022), '.$this->vn($u['2026']).' m (2026), '.$this->vn($u['2025']).' m ở các năm còn lại', $text);
        $this->assertStringContainsString('lấy '.$this->vn($this->config('U_CLASSIFY_DEFAULT'), 1).' m', $text);

        $this->assertStringContainsString('F1 trung bình MNDWI '.$this->vn($cc['mean_f1_all']['mndwi'], 4), $text);
        // khoảng sai lệch đường bờ đối chiếu chéo nêu trong hộp hạn chế
        $mean = $rmse = [];
        foreach ($cc['by_source'] as $years) {
            foreach ($years as $d) {
                $mean[] = $d['mndwi']['shoreline_offset']['mean_m'] ?? $d['shoreline_offset']['mean_m'];
                $rmse[] = $d['mndwi']['shoreline_offset']['rmse_m'] ?? $d['shoreline_offset']['rmse_m'];
            }
        }
        $this->assertStringContainsString('trung bình '.$this->vn(min($mean), 1).' đến '.$this->vn(max($mean), 1).' m, RMSE '.$this->vn(min($rmse), 1).' đến '.$this->vn(max($rmse), 1).' m', $text);
    }

    public function test_so_lieu_buoc_10_khop_validation(): void
    {
        $text = $this->text();
        $v = $this->pj('output/validation.json');
        $ev = $v['events'][0];

        $this->assertStringContainsString($this->vn($ev['nearest_hotspot_m'], 0).' m (ngưỡng khớp '.$ev['radius_m'].' m)', $text);
        $this->assertStringContainsString('Trong vùng đệm '.(int) $this->config('ZONE_BUFFER').' m có '.$ev['transects_in_zone'].' transect ('.$ev['classes']['accretion'].' bồi, '.$ev['classes']['stable'].' ổn định)', $text);
        $this->assertStringContainsString('Độ dài vùng cảnh báo '.$this->vn($ev['zone_length_m'], 0).' m', $text);
        $this->assertStringContainsString('LRR trung bình +'.$this->vn($ev['mean_lrr_m_per_year']).' m/năm', $text);
        $this->assertStringContainsString($this->vn($ev['share_eroding'] * 100, 1).'% ('.(int) round($ev['share_eroding'] * $ev['transects_in_zone']).'/'.$ev['transects_in_zone'].')', $text);
        $this->assertGreaterThan(0, (int) round($ev['share_eroding'] * $ev['transects_in_zone']));
        $this->assertSame(0.0, (float) $ev['share_significant_erosion']);
        $this->assertStringContainsString('Bán kính khớp '.(int) $this->config('MATCH_RADIUS').' m', $text);
        $this->assertStringContainsString($v['matched'].'/'.$v['total'].' sự kiện', $text);
    }

    public function test_buoc_xuat_dung_voi_lenh_nap_va_script_geoserver(): void
    {
        $page = $this->page();
        $imp = (string) file_get_contents(base_path('app/Console/Commands/ImportPipelineOutput.php'));
        $sh = (string) file_get_contents(base_path('geoserver/provision.sh'));

        $this->assertStringContainsString("'webgis:import {dir", $imp);
        $this->assertStringContainsString('--dry-run', $imp);
        $this->assertStringContainsString('ON CONFLICT (year, water_index)', $imp);
        $this->assertStringContainsString('ON CONFLICT (transect_code)', $imp);
        $this->assertStringContainsString('ON CONFLICT (transect_id, year)', $imp);
        $this->assertStringContainsString('php artisan webgis:import ../pipeline/output', $page);
        $this->assertStringContainsString('UPSERT theo khoá riêng', $page);

        foreach (['shorelines', 'erosion_hotspots', 'reference_landslide_events', 'transects_styled'] as $name) {
            $this->assertStringContainsString($name, $sh, "provision.sh thiếu $name");
            $this->assertStringContainsString($name, $page, "trang chưa nhắc $name");
        }
        foreach (['change_class', 'shoreline', 'hotspot', 'reference'] as $sld) {
            $this->assertFileExists(base_path("geoserver/styles/$sld.sld"));
            $this->assertStringContainsString("<code>$sld</code>", $page);
        }
        $this->assertStringContainsString('maxFeatures\":5000', $sh);
        $this->assertStringContainsString('tối đa 5.000 đối tượng', $page);
        $this->assertStringContainsString('nen_ban_do', $sh);
        $this->assertStringContainsString('provision.sh songhau', $page);
    }

    /** Độ dài đường gấp khúc theo toạ độ phẳng (mét, EPSG:32648). */
    private function lineLength(array $coords): float
    {
        $sum = 0.0;
        for ($i = 1; $i < count($coords); $i++) {
            $sum += hypot($coords[$i][0] - $coords[$i - 1][0], $coords[$i][1] - $coords[$i - 1][1]);
        }

        return $sum;
    }
}
