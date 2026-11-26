"""alt_compare: chọn phương pháp tách đường bờ (QGIS hay Python) cho bản đối chứng. Dữ liệu tổng hợp trong thư mục tạm."""
import pytest

pytest.importorskip("rioxarray")
gpd = pytest.importorskip("geopandas")
from shapely.geometry import LineString, MultiLineString  # noqa: E402

import alt_compare as AC  # noqa: E402
import config as C  # noqa: E402


def _write(path, x, year=2020):
    path.parent.mkdir(parents=True, exist_ok=True)
    gpd.GeoDataFrame({"year": [year]}, geometry=[MultiLineString([LineString([(x, 0), (x, 100)])])], crs=C.CRS).to_file(path, driver="GeoJSON")


@pytest.fixture()
def alt(tmp_path, monkeypatch):
    monkeypatch.setattr(AC, "ALT", tmp_path)
    return tmp_path


def test_alt_line_reads_the_directory_of_the_requested_engine(alt):
    _write(alt / "s2c1" / "shoreline" / "2020_mndwi.json", 10)
    _write(alt / "s2c1" / "qgis_shoreline" / "2020.json", 20)
    assert AC.alt_line("s2c1", 2020, engine="python").bounds[0] == pytest.approx(10)
    assert AC.alt_line("s2c1", 2020, engine="qgis").bounds[0] == pytest.approx(20)
    assert AC.alt_line("s2c1", 2021, engine="qgis") is None and AC.alt_line("nope", 2020) is None


def test_alt_line_python_engine_honours_the_requested_index(alt):
    _write(alt / "landsat" / "shoreline" / "2020_awei.json", 33)
    assert AC.alt_line("landsat", 2020, idx="awei").bounds[0] == pytest.approx(33)
    assert AC.alt_line("landsat", 2020) is None   # mặc định mndwi: không có file


def test_default_engine_is_qgis_only_when_configured_and_every_source_has_qgis_lines(alt, monkeypatch):
    for n in AC.NAMES:
        (alt / n).mkdir()
        (alt / n / "scenes.json").write_text("{}")
    monkeypatch.setattr(C, "SHORELINE_ENGINE", "qgis")
    assert AC.default_engine() == "python"                 # chưa chạy qgis_interpret_alt.py
    (alt / "s2c1" / "qgis_shoreline").mkdir()
    assert AC.default_engine() == "python"                 # mới có một nguồn: không trộn phương pháp giữa hai nguồn
    (alt / "landsat" / "qgis_shoreline").mkdir()
    assert AC.default_engine() == "qgis"
    monkeypatch.setattr(C, "SHORELINE_ENGINE", "python")
    assert AC.default_engine() == "python"                 # bản chính dùng Python thì bản đối chứng cũng Python


def test_line_offsets_follow_the_engine_and_skip_years_without_both_lines(alt, monkeypatch):
    _write(alt / "s2c1" / "shoreline" / "2020_mndwi.json", 70)
    _write(alt / "s2c1" / "qgis_shoreline" / "2020.json", 52)
    monkeypatch.setattr(AC, "main_line", lambda y: MultiLineString([LineString([(50, -10), (50, 110)])]) if y == 2020 else None)
    tr = [LineString([(0, 50), (200, 50)])]
    assert AC.line_offsets("s2c1", tr, "qgis") == {2020: pytest.approx(AC.offsets(AC.alt_line("s2c1", 2020, engine="qgis"), AC.main_line(2020), tr))}
    assert AC.line_offsets("s2c1", tr, "qgis")[2020]["mean_m"] == pytest.approx(2.0)
    assert AC.line_offsets("s2c1", tr, "python")[2020]["mean_m"] == pytest.approx(20.0)
    assert set(AC.line_offsets("s2c1", tr, "python")) == {2020}    # các năm khác không có đường bờ nào


def test_alt_compare_json_records_engine_and_python_sensitivity(output_dir, load_json):
    r = load_json(output_dir / "alt_compare.json")
    for name, v in r.items():
        assert v["engine"] in {"qgis", "python"}
        if v["engine"] == "qgis":
            assert set(v["sensitivity_python"]) == {"offsets", "trend"}
