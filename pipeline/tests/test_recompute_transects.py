"""Tái tính độc lập 266 transect từ output/transects.json bằng scipy.stats.linregress rồi so với số đã công bố."""
import math

import pytest

sps = pytest.importorskip("scipy.stats")
pytest.importorskip("numpy")

import config as C  # noqa: E402


@pytest.fixture(scope="module")
def transects(output_dir, load_json):
    return load_json(output_dir / "transects.json")["features"]


@pytest.fixture(scope="module")
def summary(output_dir, load_json):
    return load_json(output_dir / "summary.json")


def _series(props):
    return sorted((int(y), d) for y, d in props["distances"].items() if d is not None)


def _longest_decline(series):
    best = run = 0
    for (y0, d0), (y1, d1) in zip(series, series[1:]):
        run = run + 1 if (y1 == y0 + 1 and d1 < d0) else 0
        best = max(best, run)
    return best


def _expected_class(lr, n, cons, tc):
    se = lr.stderr
    if not abs(lr.slope) > tc * se:
        return "stable"
    if lr.slope > 0:
        return "accretion"
    if round(lr.slope, 3) <= C.SEVERE_LRR and cons >= C.MIN_CONSECUTIVE_DECLINE - 1:
        return "severe_erosion"
    return "mild_erosion"


def test_there_are_266_transects(transects):
    assert len(transects) == 266


def test_recompute_266_transects_with_linregress(transects):
    checked = 0
    counts = {}
    for f in transects:
        p = f["properties"]
        st = p["stats"]
        assert st is not None, p["transect_code"]
        s = _series(p)
        years = [y for y, _ in s]
        vals = [d for _, d in s]
        lr = sps.linregress(years, vals)
        n = len(s)
        code = p["transect_code"]

        assert st["n_years"] == n, code
        assert st["lrr_m_per_year"] == pytest.approx(lr.slope, abs=6e-4), code
        # chuỗi hằng (đường bờ QGIS đi theo cạnh pixel nên có transect cắt cùng một cạnh mọi năm): linregress trả stderr = nan, thực tế 0
        se = 0.0 if len(set(vals)) == 1 else lr.stderr
        assert st["se_lrr"] == pytest.approx(se, abs=6e-5), code
        assert st["r_squared"] == pytest.approx(0.0 if len(set(vals)) == 1 else lr.rvalue ** 2, abs=6e-4), code
        tc = float(sps.t.ppf(0.975, n - 2))
        assert st["t_crit"] == pytest.approx(tc, abs=6e-4), code
        assert st["unc_lrr"] == pytest.approx(tc * se, abs=2e-3), code
        assert st["nsm_m"] == pytest.approx(vals[-1] - vals[0], abs=6e-3), code
        assert st["epr_m_per_year"] == pytest.approx((vals[-1] - vals[0]) / (years[-1] - years[0]), abs=6e-4), code
        assert st["consecutive_erosion_periods"] == _longest_decline(s), code

        cls = _expected_class(lr, n, st["consecutive_erosion_periods"], tc)
        # Ranh giới |slope| == t*se chỉ lệch vì làm tròn; không có transect nào sát ranh giới nên so thẳng.
        assert st["change_class"] == cls, code
        counts[cls] = counts.get(cls, 0) + 1
        checked += 1
    assert checked == 266
    assert counts == {"accretion": 71, "stable": 181, "mild_erosion": 10, "severe_erosion": 4}


def test_recomputed_classes_match_summary(transects, summary):
    got = {}
    for f in transects:
        c = f["properties"]["stats"]["change_class"]
        got[c] = got.get(c, 0) + 1
    assert summary["transects"] == 266
    assert summary["classes"] == got
    assert summary["classes"] == {"accretion": 71, "stable": 181, "mild_erosion": 10, "severe_erosion": 4}
    assert sum(summary["classes"].values()) == 266


def test_significance_flag_matches_ci_excluding_zero(transects):
    for f in transects:
        st = f["properties"]["stats"]
        if abs(abs(st["lrr_m_per_year"]) - st["unc_lrr"]) < 2e-3:
            continue  # sát ranh giới, tránh sai lệch do làm tròn
        assert st["is_significant"] is (abs(st["lrr_m_per_year"]) > st["unc_lrr"]), f["properties"]["transect_code"]


def test_summary_mean_of_significant_erosion_lrr(transects, summary):
    lrrs = [f["properties"]["stats"]["lrr_m_per_year"] for f in transects
            if f["properties"]["stats"]["is_significant"] and f["properties"]["stats"]["lrr_m_per_year"] < 0]
    assert len(lrrs) == 14  # 10 mild + 4 severe
    mean = sum(lrrs) / len(lrrs)
    assert summary["M_lrr_erosion"] == pytest.approx(mean, abs=2e-3)
    sd = math.sqrt(sum((x - mean) ** 2 for x in lrrs) / (len(lrrs) - 1))
    assert summary["S_lrr_erosion"] == pytest.approx(sd, abs=2e-3)
