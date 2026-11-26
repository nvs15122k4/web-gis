"""limits.py: các phép tính thuần của 5 phân tích bổ sung (LRR có trọng số, độ lệch tham chiếu, U_classify, ngưỡng cố định, chọn chỉ số)
và tính nhất quán của output/limits_*.json."""
import pytest

pytest.importorskip("rioxarray")
np = pytest.importorskip("numpy")

import config as C  # noqa: E402
import limits as L  # noqa: E402
import s5_stats  # noqa: E402

YEARS = [2018, 2019, 2020, 2021, 2022]


# --- LRR có trọng số ---------------------------------------------------------------------------------------------
def test_weighted_lrr_equal_weights_matches_ols():
    d = [10.0, 12.5, 11.0, 15.0, 14.0]
    w = L.weighted_lrr(YEARS, d, [20.0] * 5)
    assert w["slope"] == pytest.approx(np.polyfit(YEARS, d, 1)[0])
    st = s5_stats.stats(dict(zip(YEARS, d)))
    assert w["se_resid"] == pytest.approx(st["se_lrr"], rel=1e-3)   # bằng SE của OLS (st làm tròn 4 chữ số)


def test_weighted_lrr_se_meas_formula_and_exact_line():
    w = L.weighted_lrr(YEARS, [1, 3, 5, 7, 9], [4.0] * 5)         # đường thẳng hoàn hảo, hệ số góc 2
    sxx = ((np.array(YEARS) - 2020) ** 2).sum()
    assert w["slope"] == pytest.approx(2.0)
    assert w["se_resid"] == pytest.approx(0.0, abs=1e-9)
    assert w["se_meas"] == pytest.approx(4.0 / np.sqrt(sxx))
    assert w["se_comb"] == pytest.approx(w["se_meas"])
    assert L.ols_meas_se(YEARS, [4.0] * 5) == pytest.approx(w["se_meas"])


def test_weighted_lrr_downweights_noisy_year():
    d = [0.0, 0.0, 0.0, 0.0, 100.0]                              # năm cuối là điểm ngoại lai
    low_u = L.weighted_lrr(YEARS, d, [5, 5, 5, 5, 5])["slope"]
    high_u = L.weighted_lrr(YEARS, d, [5, 5, 5, 5, 500])["slope"]
    assert abs(high_u) < abs(low_u) * 0.01


def test_u_total_matches_stage5_for_config_values():
    ucls = {y: C.U_CLASSIFY.get(y, C.U_CLASSIFY_DEFAULT) for y in C.YEARS}
    for y in C.YEARS:
        assert L.u_total(y, ucls) == pytest.approx(s5_stats.u_shoreline(y))


@pytest.mark.parametrize("lrr,sig,per,expected", [
    (0.5, False, 0, "stable"), (1.0, True, 0, "accretion"), (-1.0, True, 5, "mild_erosion"),
    (-2.0, True, 1, "mild_erosion"), (-2.0, True, 2, "severe_erosion"), (-3.0, False, 8, "stable")])
def test_classify_one_rules(lrr, sig, per, expected):
    assert L.classify_one(lrr, sig, per) == expected


def test_classify_one_agrees_with_stage5_classify():
    rows = [{"stats": {"lrr_m_per_year": l, "is_significant": s, "consecutive_erosion_periods": p, "r_squared": 0.5}}
            for l, s, p in [(0.5, False, 0), (1.0, True, 0), (-1.0, True, 5), (-2.5, True, 2), (-2.5, True, 1)]]
    s5_stats.classify(rows)
    for r in rows:
        st = r["stats"]
        assert st["change_class"] == L.classify_one(st["lrr_m_per_year"], st["is_significant"], st["consecutive_erosion_periods"])


# --- độ lệch, cụm, điểm cắt không --------------------------------------------------------------------------------
def test_offset_stats_tail_share():
    d = [5.0] * 18 + [100.0, 120.0]
    s = L.offset_stats(d)
    assert s["n_over_50m"] == 2 and s["rmse_excl_over_50m"] == pytest.approx(5.0)
    assert s["median_m"] == 5.0 and s["ss_share_over_50m"] > 0.97
    assert L.offset_stats([]) is None


def test_clusters_groups_by_gap():
    assert L.clusters([100, 150, 200, 900, 950, 5000]) == [(100, 200, 3), (900, 950, 2), (5000, 5000, 1)]
    assert L.clusters([]) == []


def test_zero_crossing_interpolates_and_none_without_sign_change():
    assert L.zero_crossing([0, 1, 2], [4.0, 2.0, -2.0]) == pytest.approx(1.5)
    assert L.zero_crossing([0, 1, 2], [4.0, 3.0, 1.0]) is None


# --- so sánh hai lần chạy ---------------------------------------------------------------------------------------
def _st(lrr, sig, cls):
    return {"lrr_m_per_year": lrr, "is_significant": sig, "change_class": cls}


def test_compare_trends_counts_changes():
    a = {f"T{i}": _st(i * 0.1, i > 5, "accretion" if i > 5 else "stable") for i in range(12)}
    b = {k: dict(v) for k, v in a.items()}
    assert L.compare_trends(a, b)["n_class_changed"] == 0
    b["T7"] = _st(0.2, False, "stable")
    r = L.compare_trends(a, b)
    assert r["n_class_changed"] == 1 and r["changed_transects"][0]["transect"] == "T7"
    assert r["n_significance_changed"] == 1
    assert r["confusion_a_rows_b_cols"]["accretion"]["stable"] == 1
    assert L.compare_trends(a, {})["n_transects"] == 0


# --- chọn chỉ số --------------------------------------------------------------------------------------------------
def _report(f1, rmse):
    out = {}
    for y in ("2018", "2022"):
        out[y] = {i: {"f1": f1[i]} for i in C.WATER_INDICES}
        out[y]["shoreline_error"] = {i: {"rmse_m": rmse[i]} for i in C.WATER_INDICES}
    return out


def test_sweep_tie_flips_exactly_at_f1_gap():
    rep = _report({"ndwi": 0.90, "mndwi": 0.9456, "awei": 0.9457}, {"ndwi": 28.0, "mndwi": 18.0, "awei": 60.0})
    s = L.sweep_tie(rep, [0.0, 0.00005, 0.0001, 0.005])
    assert s["0.0"] == "awei" and s["5e-05"] == "awei"
    assert s["0.0001"] == "mndwi" and s["0.005"] == "mndwi"


def test_leave_out_reports_selection():
    rep = _report({"ndwi": 0.90, "mndwi": 0.9456, "awei": 0.9457}, {"ndwi": 28.0, "mndwi": 18.0, "awei": 60.0})
    r = L.leave_out(rep, {"2018"})
    assert r["selected"] == "mndwi" and r["mean_rmse_m"]["awei"] == 60.0


# --- nhất quán với file kết quả ---------------------------------------------------------------------------------
def test_wls_output_consistent(output_dir, load_json):
    d = load_json(output_dir / "limits_wls.json")
    for name, v in d["variants"].items():
        rows = v["per_transect"]
        assert v["n_transects"] == len(rows) == sum(v["classes_wls_combined"].values()) == sum(v["classes_ols"].values())
        for r in rows:
            assert r["ci_wls_combined"] >= r["ci_wls_resid"] - 1e-3          # cộng SE đường bờ không thể thu hẹp KTC
            assert r["sig_wls_combined"] == (abs(r["lrr_wls"]) > r["ci_wls_combined"])
        assert v["n_class_changed_ols_to_wls_combined"] == len(v["class_changes"])
    t94 = d["variants"]["config"]["T0094"]
    tr = load_json(output_dir / "transects.json")
    st = next(f["properties"]["stats"] for f in tr["features"] if f["properties"]["transect_code"] == "T0094")
    assert t94["epr"] == pytest.approx(st["epr_m_per_year"]) and t94["unc_epr"] == pytest.approx(st["unc_epr"])
    assert t94["sig_epr"] is False and t94["sig_ols"] is True


def test_uclassify_output_keeps_measured_years(output_dir, load_json):
    d = load_json(output_dir / "limits_uclassify.json")
    for y, v in d["measured_rmse_vs_ground_truth_m"].items():
        assert d["u_hybrid_m"][y] == v
    assert set(d["years_using_default"]) == {y for y in C.YEARS if y not in C.U_CLASSIFY}
    assert all(v > 0 for v in d["u_est_raw_m"]["1.0"].values())


def test_index_selection_output_matches_config(output_dir, load_json):
    d = load_json(output_dir / "limits_index_selection.json")
    assert d["selected_current"] == C.DEFAULT_INDEX
    gap = d["f1_gap_to_top"][C.DEFAULT_INDEX]
    assert d["selection_by_tie"]["0.0"] == d["top_by_f1"]
    first_mndwi = next(f for f in d["selection_flip_points"] if f["selected"] == C.DEFAULT_INDEX)
    assert first_mndwi["tie_from"] == pytest.approx(gap, abs=2e-5)


def test_fixed_threshold_output_shapes(output_dir, load_json):
    d = load_json(output_dir / "limits_fixed_threshold.json")
    assert set(d["variants"]) == {"fixed_0.0", "fixed_median_otsu"}
    for v in d["variants"].values():
        t = v["trend_comparison"]
        assert t["n_class_changed"] == len(t["changed_transects"])
        assert sum(t["classes_a"].values()) == sum(t["classes_b"].values()) == t["n_transects"]
        assert -1 <= t["pearson_lrr"] <= 1


def test_reference_offset_output_has_tail_evidence(output_dir, load_json):
    d = load_json(output_dir / "limits_reference_offset.json")
    wc = d["pipeline_vs_reference"]["worldcover_2020"]
    # output/crosscheck.json có thể cũ hơn đường bờ chính hiện hành (chưa chạy lại s8 sau khi đổi engine), nên chỉ kiểm cùng bậc độ lớn
    cc = load_json(output_dir / "crosscheck.json")["by_source"]["worldcover"]["2020"]["shoreline_offset"]
    assert wc["rmse_m"] == pytest.approx(cc["rmse_m"], abs=5) and wc["mean_m"] == pytest.approx(cc["mean_m"], abs=5)
    assert wc["rmse_excl_over_50m"] < wc["rmse_m"]
