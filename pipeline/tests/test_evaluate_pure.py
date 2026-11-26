"""Hàm thuần của s7_evaluate: pixel_metrics, nearest_hit, reference_shoreline. Không chạm main()/lựa chọn chỉ số."""
import pytest

pytest.importorskip("rioxarray")
np = pytest.importorskip("numpy")
gpd = pytest.importorskip("geopandas")
from shapely.geometry import LineString, Point, box  # noqa: E402

import config as C  # noqa: E402
import s7_evaluate as E  # noqa: E402


def test_pixel_metrics_known_confusion_matrix():
    pred = np.array([1, 1, 1, 0, 0, 0, 0, 0, 0, 0], bool)
    ref = np.array([1, 1, 0, 1, 0, 0, 0, 0, 0, 0], bool)   # TP=2 FP=1 FN=1
    m = E.pixel_metrics(pred, ref)
    assert m == {"precision": 0.6667, "recall": 0.6667, "f1": 0.6667, "iou": 0.5, "pixels": 10}


def test_pixel_metrics_asymmetric():
    pred = np.array([1, 1, 1, 1], bool)
    ref = np.array([1, 0, 0, 0], bool)   # TP=1 FP=3 FN=0
    m = E.pixel_metrics(pred, ref)
    assert m["precision"] == 0.25 and m["recall"] == 1.0
    assert m["f1"] == pytest.approx(2 * 0.25 / 1.25, abs=1e-4)
    assert m["iou"] == 0.25


def test_pixel_metrics_perfect_and_disjoint():
    a = np.array([1, 0, 1, 0], bool)
    perfect = E.pixel_metrics(a, a)
    assert perfect["f1"] == perfect["iou"] == 1.0
    disjoint = E.pixel_metrics(a, ~a)
    assert disjoint["f1"] == 0.0 and disjoint["iou"] == 0.0


def test_pixel_metrics_all_empty_returns_zeros_without_division_error():
    z = np.zeros(5, bool)
    assert E.pixel_metrics(z, z) == {"precision": 0.0, "recall": 0.0, "f1": 0.0, "iou": 0.0, "pixels": 5}


def test_nearest_hit_returns_distance_from_transect_origin():
    t = LineString([(0, 0), (0, 100)])
    line = LineString([(-10, 30), (10, 30)])
    assert E.nearest_hit(t, line) == pytest.approx(30.0)


def test_nearest_hit_picks_closest_of_multiple_crossings():
    t = LineString([(0, 0), (0, 100)])
    line = LineString([(-10, 70), (10, 70), (10, 40), (-10, 40)])
    assert E.nearest_hit(t, line) == pytest.approx(40.0)


def test_nearest_hit_none_when_no_intersection_or_parallel_overlap():
    t = LineString([(0, 0), (0, 100)])
    assert E.nearest_hit(t, LineString([(5, 0), (5, 100)])) is None
    assert E.nearest_hit(t, LineString([(0, 10), (0, 50)])) is None  # giao là đoạn, không phải điểm


def test_reference_shoreline_none_without_river():
    water = gpd.GeoDataFrame({"loai": ["ao", None]}, geometry=[box(0, 0, 10, 10), box(20, 0, 30, 10)])
    windows = gpd.GeoDataFrame(geometry=[box(-5, -5, 40, 15)])
    assert E.reference_shoreline(water, windows) is None


def test_reference_shoreline_excludes_window_edge_and_keeps_true_bank():
    # sông rộng 100 m (x 0..100), cửa sổ x 0..100 cắt ngang => biên trên/dưới cửa sổ không phải bờ thật
    water = gpd.GeoDataFrame({"loai": [" Song "]}, geometry=[box(0, 0, 100, 100)])
    windows = gpd.GeoDataFrame(geometry=[box(-50, 20, 150, 80)])
    ref = E.reference_shoreline(water, windows)
    # bờ thật = hai cạnh đứng x=0 và x=100 trong cửa sổ, mỗi cạnh dài ~60 m trừ phần vùng đệm 5 m ở mép cửa sổ
    assert ref.geom_type in ("MultiLineString", "LineString")
    assert ref.intersects(LineString([(0, 50), (0, 50.1)]))
    assert ref.intersects(Point(100, 50).buffer(0.1))
    assert not ref.intersects(Point(50, 20).buffer(0.1))   # đáy cửa sổ y=20 không thuộc bờ
    assert ref.length == pytest.approx(2 * (60 - 2 * 5), abs=1.0)


def _rep(f1, rmse):
    """report 1 năm: f1/rmse theo chỉ số."""
    r = {i: {"f1": f1[i]} for i in f1}
    r["shoreline_error"] = {i: ({"rmse_m": rmse[i]} if rmse.get(i) is not None else None) for i in f1}
    return {2018: r}


def test_select_index_clear_f1_winner_ignores_rmse():
    idx, _, _, _ = E.select_index(_rep({"ndwi": 0.90, "mndwi": 0.95, "awei": 0.92}, {"ndwi": 1, "mndwi": 99, "awei": 1}))
    assert idx == "mndwi"


def test_select_index_tie_uses_lowest_rmse():
    idx, why, _, _ = E.select_index(_rep({"ndwi": 0.90, "mndwi": 0.9456, "awei": 0.9457}, {"ndwi": 28, "mndwi": 17.8, "awei": 62.5}))
    assert idx == "mndwi" and "RMSE" in why


def test_select_index_tie_without_rmse_falls_back_to_f1():
    idx, _, _, _ = E.select_index(_rep({"ndwi": 0.90, "mndwi": 0.9456, "awei": 0.9457}, {}))
    assert idx == "awei"


def test_select_index_boundary_of_tie_tolerance():
    f1 = {"ndwi": 0.80, "mndwi": 0.9400, "awei": 0.9450}   # chênh đúng 0,005 -> vẫn hoà
    rm = {"ndwi": 5, "mndwi": 10, "awei": 20}
    assert E.select_index(_rep(f1, rm), tie_f1=0.005)[0] == "mndwi"
    assert E.select_index(_rep(f1, rm), tie_f1=0.004)[0] == "awei"


def test_evaluation_json_matches_config_selection(output_dir, load_json):
    e = load_json(output_dir / "evaluation.json")
    assert e["selected_index"] == C.DEFAULT_INDEX
