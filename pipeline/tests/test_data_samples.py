"""Validate dữ liệu mẫu trong pipeline/data và pipeline/output (schema, CRS, geometry, thuộc tính bắt buộc)."""
import json

import pytest

shapely = pytest.importorskip("shapely")
from shapely.geometry import shape  # noqa: E402

import config as C  # noqa: E402

CLASSES = {"accretion", "stable", "mild_erosion", "severe_erosion"}
STATS_KEYS = {"nsm_m", "epr_m_per_year", "unc_epr", "lrr_m_per_year", "se_lrr", "unc_lrr", "r_squared",
              "is_significant", "consecutive_erosion_periods", "u_shoreline_avg", "n_years", "first_year",
              "last_year", "change_class", "low_r2_caution", "despiked"}
WEB_NAMES = ["aoi", "baseline", "hotspots", "reference", "shorelines", "transects"]
LON_MIN, LAT_MIN, LON_MAX, LAT_MAX = C.BBOX


def fc_of(load_json, path):
    fc = load_json(path)
    assert fc["type"] == "FeatureCollection"
    assert fc["features"], f"{path.name} rỗng"
    return fc


def in_wgs84_area(geom):
    # cho phép lề 0.1° quanh BBOX (ranh giới xã/vùng nghiên cứu có thể vượt nhẹ)
    minx, miny, maxx, maxy = geom.bounds
    return (LON_MIN - .1 <= minx and maxx <= LON_MAX + .1 and LAT_MIN - .1 <= miny and maxy <= LAT_MAX + .1)


# ------------------------- output/*.json (EPSG:4326) -------------------------
@pytest.mark.parametrize("name", WEB_NAMES)
def test_output_geometries_valid_nonempty_wgs84(name, output_dir, load_json):
    fc = fc_of(load_json, output_dir / f"{name}.json")
    for i, f in enumerate(fc["features"]):
        assert f["type"] == "Feature"
        g = shape(f["geometry"])
        assert not g.is_empty, (name, i)
        assert g.is_valid, (name, i)
        assert in_wgs84_area(g), (name, i, g.bounds)
        assert isinstance(f["properties"], dict)


@pytest.mark.parametrize("name", WEB_NAMES)
def test_output_no_projected_crs_declared(name, output_dir, load_json):
    crs = load_json(output_dir / f"{name}.json").get("crs")
    if crs:
        assert "4326" in json.dumps(crs) or "CRS84" in json.dumps(crs)


def test_transects_schema(output_dir, load_json):
    fc = fc_of(load_json, output_dir / "transects.json")
    codes = set()
    for f in fc["features"]:
        p = f["properties"]
        assert {"transect_code", "chainage_m", "distances", "stats"} <= set(p)
        assert f["geometry"]["type"] == "LineString"
        assert p["transect_code"] not in codes
        codes.add(p["transect_code"])
        assert p["chainage_m"] > 0
        assert isinstance(p["distances"], dict)
        assert {int(y) for y in p["distances"]} <= set(C.YEARS)
        for v in p["distances"].values():
            assert v is None or 0 <= v <= C.TRANSECT_LENGTH
        st = p["stats"]
        if st is None:
            continue
        assert STATS_KEYS <= set(st), STATS_KEYS - set(st)
        assert st["change_class"] in CLASSES
        assert 0 <= st["r_squared"] <= 1
        assert C.YEARS[0] <= st["first_year"] < st["last_year"] <= C.YEARS[-1]
        assert st["n_years"] >= 3
        assert st["se_lrr"] >= 0


def test_transects_chainage_sorted_with_spacing(output_dir, load_json):
    ch = [f["properties"]["chainage_m"] for f in fc_of(load_json, output_dir / "transects.json")["features"]]
    assert ch == sorted(ch)
    assert len(set(ch)) == len(ch)


def test_transects_class_logic_consistent(output_dir, load_json):
    for f in fc_of(load_json, output_dir / "transects.json")["features"]:
        st = f["properties"]["stats"]
        if not st:
            continue
        if st["change_class"] == "stable":
            assert not st["is_significant"]
        elif st["change_class"] == "accretion":
            assert st["is_significant"] and st["lrr_m_per_year"] > 0
        else:
            assert st["is_significant"] and st["lrr_m_per_year"] < 0
        assert st["low_r2_caution"] == (st["r_squared"] < C.R2_CAUTION)
        if st["change_class"] == "severe_erosion":
            assert st["consecutive_erosion_periods"] >= C.MIN_CONSECUTIVE_DECLINE - 1


def test_shorelines_schema(output_dir, load_json):
    fc = fc_of(load_json, output_dir / "shorelines.json")
    years = [f["properties"]["year"] for f in fc["features"]]
    assert len(years) == len(set(years))
    assert set(years) <= set(C.YEARS)
    for f in fc["features"]:
        p = f["properties"]
        assert p["water_index"] == C.DEFAULT_INDEX.upper()
        assert f["geometry"]["type"] in ("LineString", "MultiLineString")
        assert str(p["year"]) in (p["source_image_date"] or "")


def test_hotspots_schema(output_dir, load_json):
    fc = fc_of(load_json, output_dir / "hotspots.json")
    for f in fc["features"]:
        p = f["properties"]
        assert {"severity", "period_start_year", "period_end_year", "notes"} <= set(p)
        assert p["severity"] > 0
        assert p["period_start_year"] <= p["period_end_year"]
        assert f["geometry"]["type"] in ("LineString", "Polygon")


def test_baseline_is_single_line(output_dir, load_json):
    fc = fc_of(load_json, output_dir / "baseline.json")
    assert len(fc["features"]) == 1
    assert fc["features"][0]["geometry"]["type"] == "LineString"


def test_aoi_has_study_area_polygon(output_dir, load_json):
    fc = fc_of(load_json, output_dir / "aoi.json")
    assert any(f["properties"].get("role") == "study_area" for f in fc["features"])
    assert all(f["geometry"]["type"] in ("Polygon", "MultiPolygon") for f in fc["features"])


def test_reference_schema(output_dir, load_json):
    for f in fc_of(load_json, output_dir / "reference.json")["features"]:
        p = f["properties"]
        assert {"event_date", "danger_level", "source", "description"} <= set(p)
        assert len(p["event_date"]) == 10 and p["event_date"][4] == "-"


def test_summary_consistent_with_transects(output_dir, load_json):
    s = load_json(output_dir / "summary.json")
    fc = fc_of(load_json, output_dir / "transects.json")
    assert s["transects"] == len(fc["features"])
    assert sum(s["classes"].values()) == s["transects"]
    assert set(s["classes"]) <= CLASSES | {"no_data"}
    got = {}
    for f in fc["features"]:
        st = f["properties"]["stats"]
        k = st["change_class"] if st else "no_data"
        got[k] = got.get(k, 0) + 1
    assert got == s["classes"]
    assert s["water_index"] == C.DEFAULT_INDEX
    assert s["hotspots"] == len(fc_of(load_json, output_dir / "hotspots.json")["features"])
    if s["M_lrr_erosion"] is not None:
        assert s["severe_threshold_M_minus_S"] == pytest.approx(s["M_lrr_erosion"] - s["S_lrr_erosion"])


def test_summary_u_shoreline_matches_formula(output_dir, load_json):
    import math
    s = load_json(output_dir / "summary.json")
    for y, u in s["u_shoreline_by_year"].items():
        cls = C.U_CLASSIFY.get(int(y), C.U_CLASSIFY_DEFAULT)
        assert u == pytest.approx(math.sqrt(C.U_PIXEL ** 2 + C.U_GEOREF ** 2 + cls ** 2), abs=0.01)


def test_evaluation_json(output_dir, load_json):
    e = load_json(output_dir / "evaluation.json")
    assert e["by_year"]
    assert e["best_index_by_mean_f1"] in C.WATER_INDICES


def test_crosscheck_json(output_dir, load_json):
    c = load_json(output_dir / "crosscheck.json")
    assert c["by_source"]
    assert set(c["mean_f1_all"]) <= set(C.WATER_INDICES)
    assert all(0 <= v <= 1 for v in c["mean_f1_all"].values())


# ------------------------- data/ (EPSG:32648) -------------------------
def _epsg(fc):
    return json.dumps(fc.get("crs", {}))


@pytest.mark.parametrize("year", C.YEARS)
def test_data_shoreline_year(year, data_dir, load_json):
    path = data_dir / "shoreline" / f"{year}.json"
    if not path.exists():
        pytest.skip(f"chưa có đường bờ {year}")
    fc = fc_of(load_json, path)
    assert "32648" in _epsg(fc)
    for f in fc["features"]:
        g = shape(f["geometry"])
        assert not g.is_empty and g.is_valid
        assert f["properties"]["year"] == year
        # toạ độ UTM mét, không phải độ
        assert g.bounds[0] > 100_000 and g.bounds[1] > 100_000


def test_data_transects_utm_and_year_keys(data_dir, load_json):
    fc = fc_of(load_json, data_dir / "transects.json")
    assert "32648" in _epsg(fc)
    for f in fc["features"]:
        g = shape(f["geometry"])
        assert g.geom_type == "LineString" and g.is_valid
        assert g.length == pytest.approx(C.TRANSECT_LENGTH, rel=0.01)
        d = f["properties"]["distances"]
        d = json.loads(d) if isinstance(d, str) else d
        assert set(map(int, d)) <= set(C.YEARS)


def test_data_transects_count_matches_output(data_dir, output_dir, load_json):
    a = len(fc_of(load_json, data_dir / "transects.json")["features"])
    b = len(fc_of(load_json, output_dir / "transects.json")["features"])
    assert a == b


def test_data_baseline(data_dir, load_json):
    fc = fc_of(load_json, data_dir / "baseline.json")
    assert "32648" in _epsg(fc)
    g = shape(fc["features"][0]["geometry"])
    assert g.geom_type == "LineString" and g.length > C.TRANSECT_SPACING


def test_data_ref_files(data_dir, load_json):
    fc = fc_of(load_json, data_dir / "ref" / "binhmy_osm.json")
    for f in fc["features"]:
        g = shape(f["geometry"])
        assert not g.is_empty
    lm = load_json(data_dir / "ref" / "landmarks.json")
    assert lm


def test_scenes_metadata(data_dir, load_json):
    sc = load_json(data_dir / "composite" / "scenes.json")
    assert sc
    for y, v in sc.items():
        assert int(y) in C.YEARS
        assert "source_image_date" in v
