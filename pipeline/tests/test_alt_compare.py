"""alt_compare: độ lệch đường bờ trên transect (offsets) và tính nhất quán của output/alt_transects.json."""
import pytest

pytest.importorskip("rioxarray")
np = pytest.importorskip("numpy")
from shapely.geometry import LineString  # noqa: E402

import alt_compare as AC  # noqa: E402


def _transects(n=3):
    # transect nằm ngang, gốc ở x=0, cách nhau 50 m theo y
    return [LineString([(0, 50 * i), (100, 50 * i)]) for i in range(n)]


def _shore(x, n=3):
    return LineString([(x, -10), (x, 50 * n)])


def test_offsets_positive_when_alt_is_farther_along_transect():
    o = AC.offsets(_shore(10), _shore(7), _transects())
    assert o["n_transects"] == 3
    assert o["mean_m"] == pytest.approx(3.0) and o["mean_abs_m"] == pytest.approx(3.0)
    assert o["rmse_m"] == pytest.approx(3.0) and o["n_over_50m"] == 0


def test_offsets_negative_and_counts_large_gaps():
    o = AC.offsets(_shore(10), _shore(70), _transects())   # alt nằm gần gốc hơn 60 m
    assert o["mean_m"] == pytest.approx(-60.0)
    assert o["n_over_50m"] == 3


def test_offsets_skips_transects_that_miss_a_line_and_returns_none_if_all_miss():
    tr = _transects(2) + [LineString([(0, 500), (100, 500)])]   # transect thứ 3 không cắt đường bờ nào
    o = AC.offsets(_shore(10, 2), _shore(7, 2), tr)
    assert o["n_transects"] == 2
    assert AC.offsets(_shore(10, 1), _shore(7, 1), [LineString([(0, 900), (100, 900)])]) is None


def test_alt_transects_diff_is_alt_minus_main(output_dir, load_json):
    d = load_json(output_dir / "alt_transects.json")
    rows = [f["properties"] for f in d["features"]]
    assert len(rows) == 266
    checked = 0
    for p in rows:
        for name in ("s2c1", "landsat"):
            a, m, diff = p.get(f"{name}_lrr"), p.get("main_lrr"), p.get(f"{name}_diff")
            if a is not None and m is not None:
                assert diff == pytest.approx(a - m, abs=2e-3)
                checked += 1
            else:
                assert diff is None
    assert checked > 400


def test_alt_compare_json_has_both_sources_and_marks_low_confidence_years(output_dir, load_json):
    r = load_json(output_dir / "alt_compare.json")
    assert set(r) == {"s2c1", "landsat"}
    assert r["s2c1"]["low_confidence_years"] == [2018, 2019, 2022]
    assert r["landsat"]["low_confidence_years"] == []
    for name in r:
        assert set(r[name]["trend"]) == {"all_years", "reliable_years"}
        assert set(r[name]["groundtruth"]["mean"]) == {"ndwi", "mndwi", "awei"}
