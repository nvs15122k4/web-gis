import pytest

import config as C


def test_paths_are_consistent():
    assert C.DATA == C.ROOT / "data"
    assert C.OUTPUT == C.ROOT / "output"


def test_bbox_valid_wgs84():
    minx, miny, maxx, maxy = C.BBOX
    assert minx < maxx and miny < maxy
    assert -180 <= minx and maxx <= 180 and -90 <= miny and maxy <= 90


def test_crs_is_utm_48n():
    assert C.CRS == "EPSG:32648"


def test_years_contiguous_and_sorted():
    assert C.YEARS == sorted(set(C.YEARS))
    assert C.YEARS[-1] - C.YEARS[0] + 1 == len(C.YEARS)


@pytest.mark.parametrize("name", ["TRANSECT_SPACING", "BASELINE_OFFSET", "TRANSECT_LENGTH", "DESPIKE_M",
                                  "U_PIXEL", "U_GEOREF", "U_CLASSIFY_DEFAULT", "RESOLUTION", "SMOOTH_TOLERANCE"])
def test_positive_parameters(name):
    assert getattr(C, name) > 0


def test_transect_longer_than_offset():
    assert C.TRANSECT_LENGTH > C.BASELINE_OFFSET


def test_default_index_is_known():
    assert C.DEFAULT_INDEX in C.WATER_INDICES


def test_season_format():
    for s in C.SEASON:
        m, d = s.split("-")
        assert 1 <= int(m) <= 12 and 1 <= int(d) <= 31


def test_cloud_thresholds_in_range():
    assert 0 < C.MAX_MASKED_AOI < 1
    assert 0 < C.PREFILTER_CLOUD <= 100


def test_scl_mask_values_valid():
    assert all(0 <= v <= 11 for v in C.SCL_MASK)
    assert len(set(C.SCL_MASK)) == len(C.SCL_MASK)


def test_classification_params():
    assert C.Z95 == pytest.approx(1.96)
    assert C.MIN_CONSECUTIVE_DECLINE >= 2
    assert 0 < C.R2_CAUTION < 1


def test_u_classify_years_in_range_and_positive():
    for y, u in C.U_CLASSIFY.items():
        assert y in C.YEARS
        assert u > 0
