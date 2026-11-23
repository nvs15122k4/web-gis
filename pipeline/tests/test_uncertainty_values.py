"""Giá trị tuyệt đối của U_shoreline theo năm và t_crit so với scipy (độc lập với công thức trong s5_stats)."""
import pytest

pytest.importorskip("geopandas")
sps = pytest.importorskip("scipy.stats")

import config as C  # noqa: E402
import s5_stats as S  # noqa: E402


# Giá trị chốt từ tính tay: sqrt(5² + 6² + U_classify²)
@pytest.mark.parametrize("year,expected", [
    (2018, 17.00),   # sqrt(25+36+15.1²) = 17.00
    (2019, 21.47),   # năm không có ground truth: U_CLASSIFY_DEFAULT = 20
    (2020, 21.47),
    (2021, 21.47),
    (2022, 19.98),   # sqrt(25+36+18.39²)
    (2023, 21.47),
    (2024, 21.47),
    (2025, 21.47),
    (2026, 21.46),   # sqrt(25+36+19.99²)
])
def test_u_shoreline_year_values(year, expected):
    assert S.u_shoreline(year) == pytest.approx(expected, abs=0.005)


def test_u_shoreline_matches_published_summary(output_dir, load_json):
    published = load_json(output_dir / "summary.json")["u_shoreline_by_year"]
    assert set(published) == {str(y) for y in C.YEARS}
    for y, v in published.items():
        assert S.u_shoreline(int(y)) == pytest.approx(v, abs=0.005)


def test_u_shoreline_unclassified_year_uses_conservative_max():
    assert C.U_CLASSIFY_DEFAULT >= max(C.U_CLASSIFY.values())
    assert S.u_shoreline(2019) > S.u_shoreline(2018)


@pytest.mark.parametrize("n", range(3, 61))
def test_t_crit_matches_scipy_ppf(n):
    lo, hi = sps.t.interval(0.95, n - 2)
    assert S.t_crit(n) == pytest.approx(hi, rel=1e-9)
    assert S.t_crit(n) == pytest.approx(-lo, rel=1e-9)
    assert S.t_crit(n) == pytest.approx(sps.t.ppf(0.975, n - 2), rel=1e-9)


def test_t_crit_uses_df_n_minus_2_not_z():
    assert S.t_crit(9) == pytest.approx(2.3646, abs=1e-3)  # df=7
    assert S.t_crit(9) > C.Z95
    assert S.t_crit(3) == pytest.approx(12.7062, abs=1e-3)  # df=1
