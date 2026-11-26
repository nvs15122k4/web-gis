"""alt_data: mặt nạ QA Landsat, đổi DN -> phản xạ cho Landsat C2 và Sentinel-2 C1. Dữ liệu tổng hợp, không cần mạng."""
from types import SimpleNamespace

import pytest

np = pytest.importorskip("numpy")
xr = pytest.importorskip("xarray")
pytest.importorskip("rioxarray")

import alt_data as A  # noqa: E402


def _ds(**arrs):
    return xr.Dataset({k: (("time", "y", "x"), np.array(v).reshape(1, 1, -1)) for k, v in arrs.items()})


def test_landsat_qa_mask_flags_cloud_shadow_snow_fill_not_clear():
    # bit: 0 fill, 1 dilated cloud, 2 cirrus, 3 cloud, 4 shadow, 5 snow, 6 clear
    qa = [0, 1 << 6, 0b0101010101000000, 1 << 0, 1 << 1, 1 << 2, 1 << 3, 1 << 4, 1 << 5]
    bad = A._mask("landsat", _ds(qa_pixel=qa)).values.ravel()
    assert bad.tolist() == [False, False, False, True, True, True, True, True, True]


def test_s2c1_mask_uses_scl_classes_from_config():
    import config as C
    scl = [4, 5, 6, 0, 3, 8, 9, 10, 11]
    bad = A._mask("s2c1", _ds(scl=scl)).values.ravel()
    assert bad.tolist() == [s in C.SCL_MASK for s in scl]


def test_landsat_reflectance_scale_offset_nodata_and_clip():
    dn = np.array([0, 7273, 10000, 65535], dtype="uint16")
    ds = _ds(green=dn, nir08=dn, swir16=dn, swir22=dn)
    out = A._reflectance("landsat", ds, items=[])
    g = out["B03"].values.ravel()
    assert np.isnan(g[0])                              # DN 0 = không dữ liệu
    assert g[1] == pytest.approx(0.0, abs=1e-4)        # 7273 * 2.75e-5 - 0.2 ≈ 0
    assert g[2] == pytest.approx(0.075, abs=1e-6)
    assert g[3] == 1.0                                 # kẹp về <= 1
    assert set(out) == {"B03", "B08", "B11", "B12"}


def test_s2c1_reflectance_reads_offset_from_item_metadata():
    asset = SimpleNamespace(extra_fields={"raster:bands": [{"scale": 0.0001, "offset": -0.1}]})
    item = SimpleNamespace(assets={"green": asset})
    dn = np.array([0, 1000, 2000], dtype="uint16")
    ds = _ds(green=dn, nir=dn, swir16=dn, swir22=dn)
    out = A._reflectance("s2c1", ds, items=[item])
    v = out["B03"].values.ravel()
    assert np.isnan(v[0])
    assert v[1] == pytest.approx(0.0, abs=1e-6)        # 1000 * 1e-4 - 0.1
    assert v[2] == pytest.approx(0.1, abs=1e-6)


def test_sources_declare_resolution_and_required_bands():
    assert A.SOURCES["s2c1"]["res"] == 10 and A.SOURCES["landsat"]["res"] == 30
    for cfg in A.SOURCES.values():
        assert cfg["bands"][-1] in {"scl", "qa_pixel"}   # băng cuối là băng chất lượng dùng cho mặt nạ
