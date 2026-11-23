"""Kiểm văn phong "dự kiến" và bảng kế hoạch 15 tuần của đề cương đồ án.

Đề cương là văn bản lập TRƯỚC khi thực nghiệm nên không được khẳng định kết
quả cụ thể. Các test đọc file .docx qua pandoc (``-t plain --wrap=none``).

Đường dẫn docx mặc định: ``docs/tai-lieu/...docx`` tính từ gốc repo; ghi đè
bằng biến môi trường ``DECUONG_DOCX``. Thiếu docx (``docs/`` bị gitignore) hoặc
thiếu pandoc thì các test dùng tài liệu thật sẽ skip kèm thông báo; các test
đơn vị trên chuỗi mẫu vẫn luôn chạy.
"""

import os
import re
import shutil
import subprocess
from datetime import date, datetime, timedelta
from pathlib import Path

import pytest

DOCX_NAME = "1150080072_Nguyen_Van_Sang_De_Cuong_DATN_WebGIS_SongHau.docx"
DEFAULT_DOCX = Path(__file__).resolve().parents[2] / "docs/tai-lieu" / DOCX_NAME

# (khoá, regex khớp TOÀN BỘ dòng tiêu đề) theo đúng thứ tự xuất hiện trong đề cương.
SECTION_HEADINGS = [
    ("I", r"I\.\s+Mở đầu"),
    ("II", r"II\.\s+Nội dung"),
    ("loi_mo_dau", r"LỜI MỞ ĐẦU"),
    ("chuong1", r"Chương 1\.\s+TỔNG QUAN TÀI LIỆU"),
    ("chuong2", r"Chương 2\.\s+PHƯƠNG PHÁP THỰC HIỆN"),
    ("chuong3", r"Chương 3\.\s+CÀI ĐẶT THỰC NGHIỆM"),
    ("3.1", r"3\.1\.\s+Môi trường và công cụ cài đặt"),
    ("3.2", r"3\.2\.\s+Cài đặt pipeline xử lý ảnh Sentinel-2"),
    ("3.3", r"3\.3\.\s+Kết quả vector hóa đường bờ và phân tích biến động"),
    ("3.4", r"3\.4\.\s+Kết quả đánh giá độ chính xác và kiểm chứng thực tế"),
    ("3.5", r"3\.5\.\s+Cài đặt hệ thống WebGIS"),
    ("3.6", r"3\.6\.\s+Kiểm thử hệ thống bằng Playwright"),
    ("3.7", r"3\.7\.\s+Một số giao diện minh họa"),
    ("ket_luan", r"KẾT LUẬN"),
    ("III", r"III\.\s+DỰ KIẾN KẾ HOẠCH THỰC HIỆN"),
    ("IV", r"IV\.\s+TÀI LIỆU THAM KHẢO"),
    ("V", r"V\.\s+CÁC CƠ QUAN, ĐƠN VỊ CẦN LIÊN HỆ"),
]

# Cụm KHẲNG ĐỊNH kết quả cụ thể (không cấm chữ "Kết quả" nói chung: tiêu đề mục
# 3.3/3.4 có chữ đó và "kết quả dự kiến" là cách nói hợp lệ).
FORBIDDEN_PHRASES = [
    r"kết quả (thực nghiệm )?cho thấy",
    r"kết quả (thu được|đạt được) (là|như sau)",
    r"đã xác định được",
    r"đã hoàn thành",
    r"đã đạt (được )?(độ chính xác|F1|IoU)",
    r"đã thu thập (được )?\d",
    r"phát hiện (được )?\d+ (đoạn|transect)",
    r"xác định (được )?\d+ (đoạn|transect)",
    r"(F1-score|F1|IoU|Precision|Recall|RMSE)\s*(đạt|là|bằng|=|:)\s*\d",
    r"độ chính xác (đạt|là|bằng)\s*\d",
    r"thực nghiệm (đã )?(chứng minh|khẳng định)",
]

DATE_RE = r"(\d{2}/\d{2}/\d{4})"
WEEK_RE = re.compile(
    rf"^\s*Tuần\s+(\d+)\s*:\s*{DATE_RE}\s*[–-]\s*{DATE_RE}", re.MULTILINE
)

PLANNED_MARKERS = ("dự kiến", "sẽ ")
RESULT_SECTIONS = ["3.2", "3.3", "3.4", "3.5", "3.6", "3.7"]


def _norm(line):
    return re.sub(r"\s+", " ", line).strip()


def split_sections(text, headings=SECTION_HEADINGS):
    """Tách văn bản theo tiêu đề; trả dict khoá -> nội dung, giữ thứ tự xuất hiện.

    Mỗi tiêu đề phải xuất hiện đúng một lần (khớp nguyên dòng) và theo đúng
    thứ tự khai báo; nếu không sẽ ném AssertionError nêu rõ tiêu đề lỗi.
    """
    lines = text.splitlines()
    positions = []
    for key, pattern in headings:
        rx = re.compile(pattern)
        hits = [i for i, ln in enumerate(lines) if rx.fullmatch(_norm(ln))]
        assert hits, f"không thấy tiêu đề mục {key!r} (mẫu {pattern!r})"
        assert len(hits) == 1, f"tiêu đề mục {key!r} xuất hiện {len(hits)} lần ở dòng {hits}"
        positions.append((key, hits[0]))
    for (k1, p1), (k2, p2) in zip(positions, positions[1:]):
        assert p1 < p2, f"sai thứ tự: mục {k1!r} (dòng {p1 + 1}) phải đứng trước {k2!r} (dòng {p2 + 1})"
    sections = {}
    for idx, (key, start) in enumerate(positions):
        end = positions[idx + 1][1] if idx + 1 < len(positions) else len(lines)
        sections[key] = "\n".join(lines[start + 1 : end])
    return sections


def find_forbidden(text, patterns=FORBIDDEN_PHRASES):
    """Trả danh sách (mẫu, số dòng, nội dung dòng) cho mọi dòng vi phạm."""
    found = []
    for pattern in patterns:
        rx = re.compile(pattern, re.IGNORECASE)
        for i, ln in enumerate(text.splitlines(), start=1):
            if rx.search(ln):
                found.append((pattern, i, ln.strip()))
    return found


def parse_weeks(text):
    """Trả list (số tuần, ngày bắt đầu, ngày kết thúc) từ các dòng 'Tuần N: dd/mm/yyyy – dd/mm/yyyy'."""
    weeks = []
    for m in WEEK_RE.finditer(text):
        n = int(m.group(1))
        start = datetime.strptime(m.group(2), "%d/%m/%Y").date()
        end = datetime.strptime(m.group(3), "%d/%m/%Y").date()
        weeks.append((n, start, end))
    return weeks


def check_weeks_range(weeks):
    assert len(weeks) == 15, f"cần đúng 15 dòng tuần, tìm thấy {len(weeks)}"
    assert [w[0] for w in weeks] == list(range(1, 16)), f"số tuần phải là 1..15 liên tiếp, có {[w[0] for w in weeks]}"
    assert weeks[0][1] == date(2026, 9, 7), f"tuần 1 phải bắt đầu 07/09/2026, có {weeks[0][1]}"
    assert weeks[-1][2] == date(2026, 12, 18), f"tuần 15 phải kết thúc 18/12/2026, có {weeks[-1][2]}"


def check_weeks_continuous(weeks):
    for n, start, end in weeks:
        assert start.weekday() == 0, f"tuần {n} phải bắt đầu thứ Hai, có {start} (thứ {start.weekday() + 2})"
        assert end.weekday() == 4, f"tuần {n} phải kết thúc thứ Sáu, có {end} (thứ {end.weekday() + 2})"
    for (n1, _, e1), (n2, s2, _) in zip(weeks, weeks[1:]):
        gap = s2 - e1
        assert gap == timedelta(days=3), f"tuần {n2} phải bắt đầu 3 ngày sau tuần {n1} ({e1}), thực tế cách {gap.days} ngày"


# ---------------------------------------------------------------------------
# Tài liệu thật (skip khi thiếu docx / pandoc)
# ---------------------------------------------------------------------------


@pytest.fixture(scope="module")
def de_cuong_text():
    path = Path(os.environ.get("DECUONG_DOCX") or DEFAULT_DOCX)
    if not path.is_file():
        pytest.skip(f"thiếu file đề cương {path} (docs/ bị gitignore); đặt DECUONG_DOCX để chạy")
    pandoc = shutil.which("pandoc")
    if pandoc is None:
        pytest.skip("thiếu pandoc trong PATH")
    out = subprocess.run(
        [pandoc, "-t", "plain", "--wrap=none", str(path)],
        capture_output=True, text=True, encoding="utf-8", check=True, timeout=60,
    )
    assert out.stdout.strip(), "pandoc trả về nội dung rỗng"
    return out.stdout


@pytest.fixture(scope="module")
def sections(de_cuong_text):
    return split_sections(de_cuong_text)


def test_sections_found(sections):
    assert list(sections) == [k for k, _ in SECTION_HEADINGS]
    for key in ("loi_mo_dau", "3.1", "ket_luan", "III"):
        assert sections[key].strip(), f"mục {key} rỗng"


@pytest.mark.parametrize("pattern", FORBIDDEN_PHRASES)
def test_forbidden_phrases_absent(de_cuong_text, pattern):
    violations = find_forbidden(de_cuong_text, [pattern])
    detail = "\n".join(f"  dòng {i}: {ln[:200]}" for _, i, ln in violations)
    assert not violations, f"đề cương chứa cụm khẳng định kết quả {pattern!r}:\n{detail}"


@pytest.mark.parametrize("key", RESULT_SECTIONS)
def test_planned_tone_present(sections, key):
    body = sections[key].lower()
    assert any(m in body for m in PLANNED_MARKERS), f"mục {key} thiếu giọng văn dự kiến ({PLANNED_MARKERS})"


def test_conclusion_defers_results_to_experiment(sections):
    body = sections["ket_luan"].lower()
    assert "dự kiến" in body
    assert "sau khi thực nghiệm" in body


def test_weeks_count_and_range(de_cuong_text):
    check_weeks_range(parse_weeks(de_cuong_text))


def test_weeks_continuous(de_cuong_text):
    check_weeks_continuous(parse_weeks(de_cuong_text))


# ---------------------------------------------------------------------------
# Kiểm chứng chính các hàm kiểm tra bằng chuỗi mẫu (luôn chạy, không cần docx)
# ---------------------------------------------------------------------------


def _sample_weeks(n=15, dash="–"):
    start = date(2026, 9, 7)
    rows = []
    for i in range(n):
        s = start + timedelta(days=7 * i)
        e = s + timedelta(days=4)
        rows.append(f"  Tuần {i + 1}: {s:%d/%m/%Y} {dash} {e:%d/%m/%Y}    Nội dung")
    return "\n".join(rows)


SAMPLE_TITLES = {
    "I": "I.  Mở đầu",
    "II": "II. Nội dung",
    "loi_mo_dau": "LỜI MỞ ĐẦU",
    "chuong1": "Chương 1. TỔNG QUAN TÀI LIỆU",
    "chuong2": "Chương 2. PHƯƠNG PHÁP THỰC HIỆN",
    "chuong3": "Chương 3. CÀI ĐẶT THỰC NGHIỆM",
    "3.1": "3.1. Môi trường và công cụ cài đặt",
    "3.2": "3.2. Cài đặt pipeline xử lý ảnh Sentinel-2",
    "3.3": "3.3. Kết quả vector hóa đường bờ và phân tích biến động",
    "3.4": "3.4. Kết quả đánh giá độ chính xác và kiểm chứng thực tế",
    "3.5": "3.5. Cài đặt hệ thống WebGIS",
    "3.6": "3.6. Kiểm thử hệ thống bằng Playwright",
    "3.7": "3.7. Một số giao diện minh họa",
    "ket_luan": "KẾT LUẬN",
    "III": "III. DỰ KIẾN KẾ HOẠCH THỰC HIỆN",
    "IV": "IV. TÀI LIỆU THAM KHẢO",
    "V": "V.  CÁC CƠ QUAN, ĐƠN VỊ CẦN LIÊN HỆ",
}


def _sample_doc(extra=""):
    return "\n".join(
        f"{SAMPLE_TITLES[key]}\n\nĐề tài dự kiến thực hiện. {extra}\n" for key, _ in SECTION_HEADINGS
    )


def test_split_sections_valid_sample():
    secs = split_sections(_sample_doc())
    assert list(secs) == [k for k, _ in SECTION_HEADINGS]
    assert "dự kiến" in secs["3.3"]


def test_split_sections_detects_missing_and_misordered():
    doc = _sample_doc()
    missing = doc.replace("KẾT LUẬN", "KẾT")
    with pytest.raises(AssertionError, match="ket_luan"):
        split_sections(missing)
    lines = doc.splitlines()
    i, j = lines.index("LỜI MỞ ĐẦU"), lines.index("II. Nội dung")
    lines[i], lines[j] = lines[j], lines[i]
    with pytest.raises(AssertionError, match="sai thứ tự"):
        split_sections("\n".join(lines))


@pytest.mark.parametrize(
    "line",
    [
        "Kết quả cho thấy F1-score đạt 0,93.",
        "Đề tài đã xác định được 81 đoạn lùi bờ mạnh.",
        "Hệ thống phát hiện 167 transect có xu hướng lùi.",
        "Độ chính xác đạt 95% trên bộ kiểm chứng.",
    ],
)
def test_find_forbidden_flags_assertive_phrases(line):
    hits = find_forbidden(f"Đoạn mở đầu hợp lệ.\n{line}\n")
    assert hits and all(h[1] == 2 for h in hits)


def test_find_forbidden_allows_planned_wording():
    ok = (
        "3.3. Kết quả vector hóa đường bờ và phân tích biến động\n"
        "Kết quả dự kiến: số lượng đoạn chỉ được biết sau khi thực nghiệm.\n"
        "Kết quả cụ thể sẽ được báo cáo sau khi thực nghiệm.\n"
    )
    assert find_forbidden(ok) == []


@pytest.mark.parametrize("dash", ["–", "-"])
def test_parse_weeks_accepts_en_dash_and_hyphen(dash):
    weeks = parse_weeks(_sample_weeks(dash=dash))
    check_weeks_range(weeks)
    check_weeks_continuous(weeks)
    assert weeks[0] == (1, date(2026, 9, 7), date(2026, 9, 11))
    assert weeks[-1] == (15, date(2026, 12, 14), date(2026, 12, 18))


def test_weeks_check_detects_missing_week():
    weeks = parse_weeks(_sample_weeks(n=14))
    with pytest.raises(AssertionError, match="15"):
        check_weeks_range(weeks)


def test_weeks_check_detects_gap_and_wrong_weekday():
    rows = _sample_weeks().splitlines()
    gap = parse_weeks("\n".join(rows[:4] + [rows[4].replace("05/10/2026", "06/10/2026").replace("09/10/2026", "10/10/2026")] + rows[5:]))
    with pytest.raises(AssertionError, match="thứ Hai"):
        check_weeks_continuous(gap)
    shifted = _sample_weeks().replace("Tuần 8: 26/10/2026 – 30/10/2026", "Tuần 8: 02/11/2026 – 06/11/2026")
    with pytest.raises(AssertionError, match="tuần 8"):
        check_weeks_continuous(parse_weeks(shifted))
