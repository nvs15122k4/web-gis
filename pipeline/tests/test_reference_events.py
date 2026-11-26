"""Dữ liệu tham chiếu sạt lở QL91 (output/reference.json): ngày sự kiện, độ dài, diễn giải đúng là VÙNG cảnh báo."""
import datetime as dt

import pytest


@pytest.fixture(scope="module")
def features(output_dir, load_json):
    return load_json(output_dir / "reference.json")["features"]


def test_reference_has_two_events(features):
    assert len(features) == 2


def test_reference_length_m_is_3377(features):
    assert [f["properties"]["length_m"] for f in features] == [3377, 3377]


def test_reference_event_dates(features):
    dates = sorted(f["properties"]["event_date"] for f in features)
    assert dates == ["2019-08-01", "2020-05-27"]
    for d in dates:
        assert dt.date.fromisoformat(d)  # ngày hợp lệ, không chỉ đúng định dạng


def test_reference_description_states_warning_zone_not_point(features):
    for f in features:
        desc = f["properties"]["description"]
        assert "vùng cảnh báo" in desc.lower()
    assert "không phải vị trí điểm sạt" in features[0]["properties"]["description"]


def test_reference_danger_level_and_location(features):
    for f in features:
        p = f["properties"]
        assert p["danger_level"] == "dac_biet_nguy_hiem"
        assert "Bình Mỹ" in p["location"]
        assert "UBND tỉnh An Giang" in p["source"]


def test_reference_geometry_is_wgs84_linestring_in_aoi(features):
    for f in features:
        g = f["geometry"]
        assert g["type"] == "LineString" and len(g["coordinates"]) >= 2
        for lon, lat in g["coordinates"]:
            assert 105.17 <= lon <= 105.36 and 10.45 <= lat <= 10.575  # trong C.BBOX
