"""s9_validate.match_events: khoảng cách hotspot–vùng cảnh báo và thống kê transect trong vùng (dữ liệu tổng hợp, mét)."""
import pytest

gpd = pytest.importorskip("geopandas")
from shapely.geometry import LineString, Point  # noqa: E402

import config as C  # noqa: E402
import s9_validate as V  # noqa: E402


def _stat(lrr, cls, sig=True):
    return {"lrr_m_per_year": lrr, "change_class": cls, "is_significant": sig}


def _frames(hot_x):
    ev = gpd.GeoDataFrame({"event_date": ["2019-08-01"]}, geometry=[LineString([(0, 0), (1000, 0)])], crs=C.CRS)
    hot = gpd.GeoDataFrame({"id": [1]}, geometry=[Point(hot_x, 0).buffer(10)], crs=C.CRS)
    tr = gpd.GeoDataFrame({"stats": [_stat(-3, "severe_erosion"), _stat(1, "accretion"), _stat(-0.5, "mild_erosion", False)]},
                          geometry=[LineString([(100, -50), (100, 50)]), LineString([(500, 40), (500, 90)]),
                                    LineString([(900, 300), (900, 400)])], crs=C.CRS)
    return ev, hot, tr


def test_hotspot_inside_radius_is_matched():
    r = V.match_events(*_frames(1000 + 10 + 300))[0]
    assert r["within_radius"] and r["nearest_hotspot_m"] == 300


def test_hotspot_outside_radius_not_matched_and_distance_rounded():
    r = V.match_events(*_frames(1000 + 10 + 5000))[0]
    assert not r["within_radius"] and r["nearest_hotspot_m"] == 5000


def test_radius_boundary_inclusive():
    ev, hot, tr = _frames(1000 + 10 + 500)
    assert V.match_events(ev, hot, tr, radius=500)[0]["within_radius"]
    assert not V.match_events(ev, hot, tr, radius=499)[0]["within_radius"]


def test_zone_transect_statistics_use_buffer():
    r = V.match_events(*_frames(5000), buffer=100)[0]
    assert r["transects_in_zone"] == 2                      # transect thứ 3 cách vùng 300 m
    assert r["classes"] == {"severe_erosion": 1, "accretion": 1}
    assert r["mean_lrr_m_per_year"] == -1.0
    assert r["share_eroding"] == 0.5 and r["share_significant_erosion"] == 0.5


def test_no_hotspot_gives_none_distance():
    ev, _, tr = _frames(0)
    r = V.match_events(ev, gpd.GeoDataFrame({"id": []}, geometry=[], crs=C.CRS), tr)[0]
    assert r["nearest_hotspot_m"] is None and r["within_radius"] is False


def test_validation_json_consistent_with_reference(output_dir, load_json):
    v = load_json(output_dir / "validation.json")
    assert v["total"] == 2 and v["matched"] == sum(e["within_radius"] for e in v["events"])
