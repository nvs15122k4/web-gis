"""Tham số đã chốt trước theo đề cương: đổi giá trị nào phải là quyết định có chủ đích, test này sẽ đỏ để buộc xem lại."""
import pytest

import config as C


def test_classification_thresholds_frozen():
    assert C.SEVERE_LRR == -2.0
    assert C.MIN_CONSECUTIVE_DECLINE == 3
    assert C.R2_CAUTION == 0.3


def test_confidence_and_coverage_factor_frozen():
    assert C.CONFIDENCE == 0.95
    assert C.Z95 == pytest.approx(1.959964, abs=1e-6)


def test_uncertainty_components_frozen():
    assert C.U_PIXEL == 5.0
    assert C.U_GEOREF_95 == 12.0 and C.GEOREF_SIGMA_K == 2.0
    assert C.U_GEOREF == 6.0   # 12 m (95,5%) quy về 1σ
    # Số đo từ ground truth cho đường bờ chính (QGIS), sau despike; xem evaluation.json (u_classify_basis).
    assert C.U_CLASSIFY == {2018: 17.15, 2022: 17.82, 2026: 21.69}
    assert C.U_CLASSIFY_DEFAULT == 21.7


def test_water_index_settings_frozen():
    assert C.WATER_INDICES == ["ndwi", "mndwi", "awei"]
    assert C.DEFAULT_INDEX == "mndwi"
    assert C.DEFAULT_INDEX in C.WATER_INDICES


def test_default_index_matches_published_summary(output_dir, load_json):
    assert load_json(output_dir / "summary.json")["water_index"] == C.DEFAULT_INDEX


def test_published_summary_records_same_thresholds(output_dir, load_json):
    s = load_json(output_dir / "summary.json")
    assert s["severe_threshold_lrr"] == C.SEVERE_LRR
    assert s["min_consecutive_decline"] == C.MIN_CONSECUTIVE_DECLINE
    assert s["confidence"] == C.CONFIDENCE
    assert s["u_georef_sigma_m"] == C.U_GEOREF
