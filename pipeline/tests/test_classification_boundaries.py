"""Biên ngưỡng phân loại: chạy qua stats() + classify() thật, không dựng sẵn kết quả trung gian khi không cần."""
import pytest

pytest.importorskip("geopandas")

import config as C  # noqa: E402
import s5_stats as S  # noqa: E402

YEARS = list(range(2018, 2027))


def linear(slope, years=YEARS):
    return {y: 100 + slope * (y - 2018) for y in years}


def classify_series(series):
    st = S.stats(series)
    S.classify([{"stats": st}])
    return st


@pytest.mark.parametrize("slope,expected", [
    (-1.99, "mild_erosion"),
    (-2.0, "severe_erosion"),   # ngưỡng bao gồm: LRR <= -2,0
    (-2.01, "severe_erosion"),
])
def test_severe_threshold_boundary_through_real_stats(slope, expected):
    st = classify_series(linear(slope))
    assert st["lrr_m_per_year"] == pytest.approx(slope, abs=1e-3)
    assert st["is_significant"] is True
    assert st["consecutive_erosion_periods"] == 8
    assert st["change_class"] == expected


def test_threshold_is_the_frozen_constant():
    assert C.SEVERE_LRR == -2.0


# Chuỗi cố định, đã kiểm: số giai đoạn giảm liên tục dài nhất lần lượt là 2 và 1, cả hai đều có ý nghĩa và LRR << -2
SERIES_TWO_PERIODS = dict(zip(YEARS, [100, 90, 80, 82, 70, 72, 60, 62, 50]))
SERIES_ONE_PERIOD = dict(zip(YEARS, [100, 95, 97, 85, 87, 75, 77, 65, 67]))


def test_periods_boundary_two_periods_is_severe():
    st = classify_series(SERIES_TWO_PERIODS)
    assert st["consecutive_erosion_periods"] == 2
    assert st["is_significant"] and st["lrr_m_per_year"] <= C.SEVERE_LRR
    assert st["change_class"] == "severe_erosion"   # 3 mốc = 2 giai đoạn liền nhau


def test_periods_boundary_one_period_is_only_mild():
    st = classify_series(SERIES_ONE_PERIOD)
    assert st["consecutive_erosion_periods"] == 1
    assert st["is_significant"] and st["lrr_m_per_year"] <= C.SEVERE_LRR
    assert st["change_class"] == "mild_erosion"


def test_min_consecutive_decline_is_frozen():
    assert C.MIN_CONSECUTIVE_DECLINE == 3


def test_severe_slope_but_not_significant_is_stable():
    row = {"stats": {"lrr_m_per_year": -5.0, "is_significant": False, "consecutive_erosion_periods": 8,
                     "r_squared": 0.9}}
    S.classify([row])
    assert row["stats"]["change_class"] == "stable"


def test_severe_slope_not_significant_end_to_end_three_points():
    # n=3 => df=1, t=12,7: dốc ~ -15 m/năm nhưng nhiễu lớn nên CI chứa 0
    st = classify_series({2018: 100.0, 2019: 60.0, 2020: 80.0, 2021: 40.0})
    assert st["lrr_m_per_year"] <= C.SEVERE_LRR
    assert st["is_significant"] is False
    assert st["change_class"] == "stable"


def test_gap_year_breaks_decline_chain_and_downgrades_class():
    full = classify_series(linear(-5.0, [2018, 2019, 2020, 2021, 2022]))
    gap = classify_series(linear(-5.0, [2018, 2019, 2021, 2022, 2024]))
    assert full["consecutive_erosion_periods"] == 4
    assert full["change_class"] == "severe_erosion"
    # thiếu 2020 và 2023: chỉ còn các cặp (2018,2019) và (2021,2022) => chuỗi dài nhất 1 giai đoạn
    assert gap["consecutive_erosion_periods"] == 1
    assert gap["is_significant"] is True and gap["lrr_m_per_year"] <= C.SEVERE_LRR
    assert gap["change_class"] == "mild_erosion"


@pytest.mark.parametrize("r2", [0.0, 0.29, 0.31, 0.95])
def test_r2_never_changes_class(r2):
    row = {"stats": {"lrr_m_per_year": -3.0, "is_significant": True, "consecutive_erosion_periods": 5,
                     "r_squared": r2}}
    S.classify([row])
    assert row["stats"]["change_class"] == "severe_erosion"
    assert row["stats"]["low_r2_caution"] is (r2 < C.R2_CAUTION)


def test_r2_zero_from_constant_series_stays_stable():
    st = classify_series({y: 50.0 for y in YEARS})
    assert st["r_squared"] == 0.0
    assert st["change_class"] == "stable"
    assert st["low_r2_caution"] is True
