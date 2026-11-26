import math

import pytest

pytest.importorskip("numpy")
pytest.importorskip("geopandas")  # s5_stats import geopandas ở mức module
pytest.importorskip("shapely")

import config as C  # noqa: E402
import s5_stats as S  # noqa: E402


def row(stats):
    return {"stats": stats}


# ---------- u_shoreline ----------
@pytest.mark.parametrize("year,cls", [(2018, 17.15), (2022, 17.82), (2026, 21.69), (2020, C.U_CLASSIFY_DEFAULT),
                                      (1900, C.U_CLASSIFY_DEFAULT)])
def test_u_shoreline_formula(year, cls):
    exp = math.sqrt(C.U_PIXEL ** 2 + C.U_GEOREF ** 2 + cls ** 2)
    assert S.u_shoreline(year) == pytest.approx(exp)


def test_u_shoreline_is_float_and_exceeds_components():
    u = S.u_shoreline(2018)
    assert isinstance(u, float)
    assert u > max(C.U_PIXEL, C.U_GEOREF)


# ---------- longest_decline ----------
@pytest.mark.parametrize("series,expected", [
    ([], 0),
    ([(2018, 10)], 0),
    ([(2018, 10), (2019, 9)], 1),
    ([(2018, 10), (2019, 11)], 0),
    ([(2018, 10), (2019, 10)], 0),                       # bằng nhau không phải giảm
    ([(2018, 10), (2019, 9), (2020, 8), (2021, 7)], 3),
    ([(2018, 10), (2019, 9), (2020, 10), (2021, 9), (2022, 8)], 2),
    ([(2018, 10), (2019, 9), (2021, 8), (2022, 7)], 1),   # thiếu năm ngắt chuỗi
    ([(2018, 10), (2020, 5), (2022, 1)], 0),
    ([(2018, 1), (2019, 2), (2020, 3)], 0),
])
def test_longest_decline(series, expected):
    assert S.longest_decline(series) == expected


# ---------- despike ----------
def test_despike_empty_and_all_none():
    assert S.despike({}) == ({}, 0)
    d = {2018: None, 2019: None}
    assert S.despike(d) == (d, 0)


def test_despike_removes_outlier():
    d = {2018: 100.0, 2019: 101.0, 2020: 102.0, 2021: 400.0, 2022: None}
    clean, n = S.despike(d)
    assert n == 1
    assert clean[2021] is None and clean[2022] is None
    assert clean[2018] == 100.0


@pytest.mark.parametrize("delta,removed", [(0, 0), (C.DESPIKE_M, 0), (C.DESPIKE_M + 0.01, 1)])
def test_despike_boundary(delta, removed):
    # trung vị của [100,100,100+delta,100,100] = 100
    d = {1: 100.0, 2: 100.0, 3: 100.0 + delta, 4: 100.0, 5: 100.0}
    _, n = S.despike(d)
    assert n == removed


def test_despike_negative_side():
    d = {1: 100.0, 2: 100.0, 3: 100.0, 4: 100.0 - C.DESPIKE_M - 1}
    _, n = S.despike(d)
    assert n == 1


def test_despike_does_not_mutate_input():
    d = {1: 100.0, 2: 100.0, 3: 900.0}
    snap = dict(d)
    S.despike(d)
    assert d == snap


# ---------- stats ----------
def test_stats_too_few_points():
    assert S.stats({}) is None
    assert S.stats({2018: 1.0, 2019: 2.0}) is None
    assert S.stats({2018: 1.0, 2019: None, 2020: 3.0}) is None


def test_stats_perfect_linear_decline():
    d = {str(y): 100 - 2 * (y - 2018) for y in range(2018, 2027)}
    st = S.stats(d)
    assert st["lrr_m_per_year"] == pytest.approx(-2.0)
    assert st["epr_m_per_year"] == pytest.approx(-2.0)
    assert st["nsm_m"] == pytest.approx(-16.0)
    assert st["r_squared"] == pytest.approx(1.0)
    assert st["is_significant"] is True
    assert st["consecutive_erosion_periods"] == 8
    assert (st["n_years"], st["first_year"], st["last_year"]) == (9, 2018, 2026)
    assert st["se_lrr"] == pytest.approx(0, abs=1e-3)


def test_stats_constant_series_not_significant():
    st = S.stats({y: 50.0 for y in range(2018, 2023)})
    assert st["lrr_m_per_year"] == 0
    assert st["r_squared"] == 0.0
    assert st["is_significant"] is False
    assert st["nsm_m"] == 0


def test_stats_accepts_string_year_keys_and_skips_none():
    d = {"2018": 10.0, "2019": None, "2020": 14.0, "2021": 16.0, "2022": 18.0}
    st = S.stats(d)
    assert st["n_years"] == 4
    assert st["nsm_m"] == pytest.approx(8.0)
    assert st["epr_m_per_year"] == pytest.approx(2.0)


def test_stats_three_points_has_se():  # noqa: D103
    st = S.stats({2018: 10.0, 2019: 12.0, 2020: 11.0})
    assert st["se_lrr"] is not None and st["n_years"] == 3


def test_stats_unsorted_input():
    a = S.stats({2020: 3.0, 2018: 1.0, 2019: 2.5, 2021: 3.5})
    b = S.stats({2018: 1.0, 2019: 2.5, 2020: 3.0, 2021: 3.5})
    assert a == b


def test_stats_epr_uncertainty_formula():
    st = S.stats({2018: 1.0, 2019: 2.0, 2020: 3.0, 2021: 4.5})
    exp = C.Z95 * math.hypot(S.u_shoreline(2018), S.u_shoreline(2021)) / 3
    assert st["unc_epr"] == pytest.approx(exp, abs=1e-3)


# ---------- t-quantile (độ tin cậy 95%, df = n − 2) ----------
@pytest.mark.parametrize("n,expected", [(3, 12.7062), (4, 4.3027), (5, 3.1824), (7, 2.5706), (9, 2.3646), (30, 2.0484)])
def test_t_crit_known_values(n, expected):
    assert S.t_crit(n) == pytest.approx(expected, abs=1e-3)


def test_t_crit_decreases_toward_normal():
    vals = [S.t_crit(n) for n in range(3, 200)]
    assert all(a > b for a, b in zip(vals, vals[1:]))
    assert vals[-1] > C.Z95


def test_stats_ci_uses_t_not_z_for_nine_years():
    d = {y: 100 - 1.2 * (y - 2018) + (0.8 if y % 2 else -0.8) * 3 for y in range(2018, 2027)}
    st = S.stats(d)
    assert st["t_crit"] == pytest.approx(2.365, abs=1e-3)
    assert st["unc_lrr"] == pytest.approx(2.365 * st["se_lrr"], abs=2e-3)
    assert st["is_significant"] is (abs(st["lrr_m_per_year"]) > st["unc_lrr"])


def test_stats_significance_differs_between_t_and_z():
    # |LRR|/SE = 2,26: vượt z = 1,96 nhưng KHÔNG vượt t(df=7) = 2,365 -> không có ý nghĩa ở 95%
    noise = [0, 3, -2, 4, -3, 1, -4, 2, 0]
    d = {2018 + i: -1.0 * i + noise[i] * 1.5 for i in range(9)}
    st = S.stats(d)
    ratio = abs(st["lrr_m_per_year"]) / st["se_lrr"]
    assert C.Z95 < ratio < st["t_crit"]
    assert st["is_significant"] is False


def test_stats_three_points_uses_df_one():
    st = S.stats({2018: 10.0, 2019: 12.0, 2020: 11.0})
    assert st["t_crit"] == pytest.approx(12.706, abs=1e-3)


def test_u_georef_is_one_sigma():
    assert C.U_GEOREF == pytest.approx(C.U_GEOREF_95 / C.GEOREF_SIGMA_K)
    assert C.U_GEOREF == pytest.approx(6.0)


def test_stats_noisy_is_not_significant():
    st = S.stats({2018: 0, 2019: 10, 2020: -10, 2021: 10, 2022: -10, 2023: 10})
    assert st["is_significant"] is False


# ---------- classify ----------
def mk(lrr, sig=True, periods=0, r2=0.9):
    return {"stats": {"lrr_m_per_year": lrr, "is_significant": sig,
                      "consecutive_erosion_periods": periods, "r_squared": r2}}


def test_classify_basic_classes():
    rows = [mk(0.1, sig=False), mk(2.0), mk(-1.0, periods=1), mk(-1.2, periods=1), mk(-1.1, periods=1)]
    S.classify(rows)
    got = [r["stats"]["change_class"] for r in rows]
    assert got[0] == "stable"
    assert got[1] == "accretion"
    assert got[2:] == ["mild_erosion"] * 3


def test_classify_severe_requires_absolute_threshold_and_consecutive():
    rows = [mk(-1.0 - 0.01 * i, periods=2) for i in range(8)] + [mk(-8.0, periods=2), mk(-8.0, periods=1)]  # cái cuối: dưới ngưỡng nhưng chuỗi giảm quá ngắn
    M, Sd = S.classify(rows)
    assert M is not None and Sd > 0
    cls = [r["stats"]["change_class"] for r in rows]
    assert cls[8] == "severe_erosion"
    assert cls[9] == "mild_erosion"
    assert cls[0] == "mild_erosion"


@pytest.mark.parametrize("lrr,expected", [
    (C.SEVERE_LRR, "severe_erosion"),            # đúng bằng ngưỡng: tính là mạnh (<=)
    (C.SEVERE_LRR + 0.001, "mild_erosion"),
    (C.SEVERE_LRR - 0.5, "severe_erosion"),
])
def test_classify_threshold_is_fixed_and_independent_of_distribution(lrr, expected):
    # ngưỡng tuyệt đối đặt trước: không đổi dù các transect khác có LRR thế nào
    for others in ([], [mk(-0.5, periods=2)] * 5, [mk(-9.0, periods=2)] * 5):
        rows = [mk(lrr, periods=2)] + [dict(o) for o in others]
        S.classify(rows)
        assert rows[0]["stats"]["change_class"] == expected


def test_classify_below_threshold_but_short_run_is_mild():
    rows = [mk(-5.0, periods=C.MIN_CONSECUTIVE_DECLINE - 2)]
    S.classify(rows)
    assert rows[0]["stats"]["change_class"] == "mild_erosion"


def test_classify_single_erosion_still_severe_by_absolute_threshold():
    rows = [mk(-3.0, periods=5), mk(1.0)]
    assert S.classify(rows) == (None, None)       # M, S mô tả cần >= 2 transect lùi
    assert rows[0]["stats"]["change_class"] == "severe_erosion"


def test_classify_r2_does_not_change_class():
    lo, hi = mk(-3.0, periods=3, r2=0.05), mk(-3.0, periods=3, r2=0.95)
    S.classify([lo, hi])
    assert lo["stats"]["change_class"] == hi["stats"]["change_class"] == "severe_erosion"
    assert lo["stats"]["low_r2_caution"] is True and hi["stats"]["low_r2_caution"] is False


def test_classify_none_stats_skipped_and_empty():
    rows = [{"stats": None}]
    assert S.classify(rows) == (None, None)
    assert "change_class" not in (rows[0]["stats"] or {})
    assert S.classify([]) == (None, None)


@pytest.mark.parametrize("r2,flag", [(0.29, True), (C.R2_CAUTION, False), (0.9, False)])
def test_classify_low_r2_flag(r2, flag):
    rows = [mk(1.0, r2=r2)]
    S.classify(rows)
    assert rows[0]["stats"]["low_r2_caution"] is flag


def test_classify_positive_significant_never_erosion():
    rows = [mk(0.001)]
    S.classify(rows)
    assert rows[0]["stats"]["change_class"] == "accretion"


# ---------- _np / _write ----------
def test_np_converter():
    import numpy as np
    assert S._np(np.int64(3)) == 3 and isinstance(S._np(np.int64(3)), int)
    assert isinstance(S._np(np.float32(1.5)), float)
    with pytest.raises(TypeError):
        S._np(object())


def test_write_roundtrip_keeps_nested_and_unicode(tmp_path, monkeypatch):
    import json

    import geopandas as gpd
    from shapely.geometry import Point
    monkeypatch.setattr(C, "OUTPUT", tmp_path)
    gdf = gpd.GeoDataFrame([{"a": {"x": 1}, "t": "Lùi bờ", "geometry": Point(1, 2)}], crs=4326)
    S._write(gdf, "t")
    fc = json.loads((tmp_path / "t.json").read_text(encoding="utf-8"))
    p = fc["features"][0]["properties"]
    assert p == {"a": {"x": 1}, "t": "Lùi bờ"}
    assert fc["features"][0]["geometry"]["coordinates"] == [1.0, 2.0]


def test_write_empty_removes_file(tmp_path, monkeypatch):
    monkeypatch.setattr(C, "OUTPUT", tmp_path)
    (tmp_path / "e.json").write_text("{}")
    S._write(None, "e")
    assert not (tmp_path / "e.json").exists()


# ---------- hotspots ----------
def _tr_gdf(specs):
    import geopandas as gpd
    from shapely.geometry import LineString
    rows = []
    for i, (chain, cls) in enumerate(specs, 1):
        rows.append({"transect_code": f"T{i:04d}", "chainage_m": chain,
                     "stats": {"change_class": cls, "lrr_m_per_year": -2.0, "first_year": 2018, "last_year": 2026},
                     "_shore": None, "geometry": LineString([(chain, 0), (chain, 100)])})
    return gpd.GeoDataFrame(rows, crs=C.CRS)


def test_hotspots_groups_adjacent_severe():
    hs = S.hotspots(_tr_gdf([(25, "stable"), (75, "severe_erosion"), (125, "severe_erosion"),
                             (175, "stable"), (225, "severe_erosion")]))
    assert len(hs) == 2
    assert "T0002" in hs[0]["notes"] and "T0003" in hs[0]["notes"]
    assert hs[0]["severity"] == 2.0
    assert hs[0]["geometry"].geom_type == "LineString"
    assert hs[1]["geometry"].geom_type == "Polygon"   # transect lẻ -> buffer


def test_hotspots_none_severe():
    assert S.hotspots(_tr_gdf([(25, "stable"), (75, "mild_erosion")])) == []


def test_hotspots_gap_splits_groups():
    hs = S.hotspots(_tr_gdf([(25, "severe_erosion"), (25 + C.TRANSECT_SPACING * 2, "severe_erosion")]))
    assert len(hs) == 2
