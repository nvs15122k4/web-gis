import pytest

shapely = pytest.importorskip("shapely")
np = pytest.importorskip("numpy")
pytest.importorskip("geopandas")
rioxarray = pytest.importorskip("rioxarray")  # s4_transects import rioxarray ở mức module

from shapely.geometry import LineString, MultiLineString, Point  # noqa: E402

import s4_transects as T  # noqa: E402


def test_distance_none_shoreline():
    assert T.distance(LineString([(0, 0), (10, 0)]), None) is None


def test_distance_no_intersection():
    assert T.distance(LineString([(0, 0), (10, 0)]), LineString([(0, 5), (10, 5)])) is None


def test_distance_single_hit_rounded():
    t = LineString([(0, 0), (100, 0)])
    assert T.distance(t, LineString([(33.333, -5), (33.333, 5)])) == 33.33


def test_distance_closest_of_multiple_hits():
    t = LineString([(0, 0), (100, 0)])
    s = MultiLineString([[(70, -5), (70, 5)], [(20, -5), (20, 5)]])
    assert T.distance(t, s) == 20.0


def test_distance_collinear_overlap_is_none():
    t = LineString([(0, 0), (10, 0)])
    assert T.distance(t, LineString([(2, 0), (8, 0)])) is None


def test_distance_touch_at_origin_is_zero():
    t = LineString([(0, 0), (10, 0)])
    assert T.distance(t, LineString([(0, -1), (0, 1)])) == 0.0


def test_chaikin_preserves_endpoints_and_grows():
    line = LineString([(0, 0), (10, 10), (20, 0)])
    out = T.chaikin(line, 2)
    assert out.coords[0] == (0, 0) and out.coords[-1] == (20, 0)
    assert len(out.coords) > len(line.coords)


def test_chaikin_zero_iterations_identity():
    line = LineString([(0, 0), (1, 1), (2, 0)])
    assert list(T.chaikin(line, 0).coords) == list(line.coords)


def test_chaikin_stays_in_hull():
    line = LineString([(0, 0), (10, 10), (20, 0)])
    assert line.convex_hull.buffer(1e-9).contains(T.chaikin(line, 3))


def test_drop_crossing_removes_crossers():
    a = ("A", LineString([(0, 0), (10, 10)]), 0)
    b = ("B", LineString([(0, 10), (10, 0)]), 1)
    c = ("C", LineString([(100, 0), (100, 10)]), 2)
    keep = T.drop_crossing([a, b, c])
    assert len(keep) == 2 and c in keep


def test_drop_crossing_empty_and_parallel():
    assert T.drop_crossing([]) == []
    ts = [(f"T{i}", LineString([(i * 50, 0), (i * 50, 100)]), i) for i in range(5)]
    assert T.drop_crossing(ts) == ts


def test_is_water_bounds_and_values():
    from affine import Affine
    water = np.zeros((4, 4), dtype=int)
    water[1, 2] = 1
    tr = Affine(10, 0, 0, 0, -10, 40)       # pixel 10 m, góc trên-trái (0,40)
    assert bool(T.is_water(Point(25, 25), water, tr)) is True
    assert bool(T.is_water(Point(5, 5), water, tr)) is False
    assert bool(T.is_water(Point(-5, 25), water, tr)) is False
    assert bool(T.is_water(Point(500, 25), water, tr)) is False
    assert bool(T.is_water(Point(25, 500), water, tr)) is False


def test_build_transects_codes_spacing_and_direction():
    from affine import Affine
    import config as C
    # baseline nằm ngang y=0, nước ở phía y>0
    water = np.zeros((200, 200), dtype=int)
    water[:100, :] = 1                     # hàng 0..99 = nước (phía trên ảnh)
    tr = Affine(10, 0, 0, 0, -10, 1000)    # y ảnh = 1000 - 10*row -> nước ở y>=0..1000
    base = LineString([(0, 0.0), (1000, 0.0)])
    out = T.build_transects(base, water, tr)
    assert [o[0] for o in out][:2] == ["T0001", "T0002"]
    chains = [o[2] for o in out]
    assert chains[0] == C.TRANSECT_SPACING / 2
    assert all(b - a == pytest.approx(C.TRANSECT_SPACING) for a, b in zip(chains, chains[1:]))
    for _, line, _ in out:
        assert line.length == pytest.approx(C.TRANSECT_LENGTH)
        assert line.coords[-1][1] > line.coords[0][1]   # hướng về phía nước
