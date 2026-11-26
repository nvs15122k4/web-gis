"""Bản data đối chứng độc lập — dùng để so sánh với bản chính (Sentinel-2 L2A từ Planetary Computer).

Hai bản, đều lấy mùa khô tháng 1–3 mỗi năm, cùng ranh giới xã, cùng chỉ số nước và ngưỡng Otsu:
  s2c1    Sentinel-2 L2A **Collection 1** (ESA xử lý lại baseline 05.xx, tinh chỉnh định vị) từ AWS Earth Search.
          Khác bản chính ở nguồn lưu trữ, phiên bản xử lý khí quyển/định vị và cách hiệu chỉnh offset (lấy từ metadata
          STAC, không tự trừ 1000). Collection 1 chỉ có 1–2 cảnh năm 2019 và 2022 -> các năm đó đánh dấu độ tin cậy thấp.
  landsat Landsat 8/9 Collection 2 Level-2 (30 m) từ Planetary Computer: khác cảm biến, khác độ phân giải, khác mặt
          nạ mây (QA_PIXEL). Sai số định vị/pixel lớn hơn Sentinel-2, dùng để kiểm tra xu hướng chứ không để thay bản chính.

Đầu ra: data/alt/<tên>/composite|water|shoreline/<năm>..., data/alt/<tên>/scenes.json.
Chạy: python alt_data.py s2c1 [năm ...] | python alt_data.py landsat [năm ...]
"""
import json
import sys

import geopandas as gpd
import numpy as np
import planetary_computer as pc
import pystac_client
import rioxarray  # noqa: F401
from odc.stac import load
from skimage.filters import threshold_otsu

import config as C
import s2_water
import s3_shoreline

ES_URL = "https://earth-search.aws.element84.com/v1"
ALT = C.DATA / "alt"
MAX_MASKED = C.MAX_MASKED_AOI

SOURCES = {
    "s2c1": {"stac": ES_URL, "collection": "sentinel-2-c1-l2a", "res": 10, "modifier": None,
             "bands": ["green", "nir", "swir16", "swir22", "scl"], "query": None},
    "landsat": {"stac": C.STAC_URL, "collection": "landsat-c2-l2", "res": 30, "modifier": pc.sign_inplace,
                "bands": ["green", "nir08", "swir16", "swir22", "qa_pixel"],
                "query": {"platform": {"in": ["landsat-8", "landsat-9"]}}},
}
# bit QA_PIXEL của Landsat C2: 0 fill, 1 mây mỏng bị giãn, 2 ti, 3 mây, 4 bóng mây, 5 tuyết
LANDSAT_BAD_BITS = 0b111111


def _mask(name, ds):
    """True = pixel bị che / không dữ liệu."""
    if name == "s2c1":
        return ds.scl.isin(C.SCL_MASK)
    qa = ds.qa_pixel.astype("uint16")
    return (qa & LANDSAT_BAD_BITS) > 0


def _reflectance(name, ds, items):
    if name == "s2c1":
        # offset/scale đọc từ metadata STAC của từng cảnh (Collection 1: scale 1e-4, offset -0.1, đã đồng nhất mọi baseline)
        bands = {"green": "B03", "nir": "B08", "swir16": "B11", "swir22": "B12"}
        scale = np.array([items[k].assets["green"].extra_fields["raster:bands"][0]["scale"] for k in range(len(items))])
        offs = np.array([items[k].assets["green"].extra_fields["raster:bands"][0]["offset"] for k in range(len(items))])
        out = {}
        for src, dst in bands.items():
            v = ds[src].astype("float32")
            v = v.where(v > 0)
            out[dst] = v * scale[:, None, None] + offs[:, None, None]
        return out
    bands = {"green": "B03", "nir08": "B08", "swir16": "B11", "swir22": "B12"}
    out = {}
    for src, dst in bands.items():
        v = ds[src].astype("float32")
        v = v.where(v > 0)
        out[dst] = (v * 0.0000275 - 0.2).clip(0, 1)   # Landsat C2 L2 surface reflectance: scale 2.75e-5, offset -0.2
    return out


def composite_year(name, cat, year):
    cfg = SOURCES[name]
    kw = {"query": cfg["query"]} if cfg["query"] else {}
    items = list(cat.search(collections=[cfg["collection"]], bbox=C.BBOX,
                            datetime=f"{year}-{C.SEASON[0]}/{year}-{C.SEASON[1]}", **kw).items())
    items.sort(key=lambda i: i.datetime)
    if not items:
        return None, []
    ds = load(items, bands=cfg["bands"], bbox=C.BBOX, crs=C.CRS, resolution=cfg["res"], groupby="solar_day",
              chunks={}, resampling={cfg["bands"][-1]: "nearest", "*": "bilinear"}).compute()
    day = {}
    for i in items:
        day.setdefault(i.datetime.date(), []).append(i)
    day_items = [day[np.datetime64(t, "D").astype(object)][0] for t in ds.time.values]
    bad = _mask(name, ds)
    # Landsat: ngoài vùng phủ của quỹ đạo cũng là "bị che" -> chỉ tính tỉ lệ trên phần có phủ (fill bit 0 = không phủ)
    covered = (ds.qa_pixel.astype("uint16") & 1) == 0 if name == "landsat" else ~(ds.scl == 0)
    frac = (bad & covered).sum(dim=("x", "y")) / covered.sum(dim=("x", "y")).clip(min=1)
    cover_share = covered.mean(dim=("x", "y")).values
    frac = frac.values
    keep = (frac <= MAX_MASKED) & (cover_share > 0.5)
    scenes = [{"date": str(np.datetime64(t, "D")), "masked_fraction": round(float(f), 3),
               "covered_share": round(float(c), 3), "used": bool(k),
               "id": it.id, "platform": it.properties.get("platform", ""),
               "processing_baseline": str(it.properties.get("s2:processing_baseline", it.properties.get("landsat:collection_category", "")))}
              for t, f, c, k, it in zip(ds.time.values, frac, cover_share, keep, day_items)]
    if not keep.any():
        return None, scenes
    sel = np.where(keep)[0]
    refl = _reflectance(name, ds.isel(time=sel), [day_items[k] for k in sel])
    clear = ~bad.isel(time=sel)
    med = np.stack([refl[b].where(clear).median(dim="time", skipna=True).values for b in ("B03", "B08", "B11", "B12")])
    like = ds.isel(time=0)
    import xarray as xr
    da = xr.DataArray(med.astype("float32"), dims=("band", "y", "x"),
                      coords={"band": [1, 2, 3, 4], "y": like.y.values, "x": like.x.values})
    return da.rio.write_crs(C.CRS), scenes


def water_and_shoreline(name, year, out):
    """Otsu cho 3 chỉ số + đường bờ theo chỉ số mặc định bằng ĐÚNG thủ tục bước 2–3 (tham số hình thái quy theo pixel)."""
    import rioxarray as rx
    a = rx.open_rasterio(out / "composite" / f"{year}.tif")
    zone = s2_water.analysis_mask(a)
    thr = {}
    (out / "water").mkdir(parents=True, exist_ok=True)
    fields = {}
    for idx_name, idx in s2_water.indices(a).items():
        valid = zone & np.isfinite(idx)
        t = float(threshold_otsu(idx[valid]))
        water = np.full(idx.shape, 255, np.uint8)
        water[np.isfinite(idx)] = (idx[np.isfinite(idx)] > t).astype(np.uint8)
        a.isel(band=0).copy(data=water).rio.write_nodata(255).rio.to_raster(out / "water" / f"{year}_{idx_name}.tif", compress="deflate")
        thr[idx_name] = round(t, 4)
        fields[idx_name] = (water, idx - t)
    res = SOURCES[name]["res"]
    k = 10 / res  # đổi hằng số pixel của bước 3 (đặt cho 10 m) sang độ phân giải này
    saved = (s3_shoreline.OPEN_RADIUS, s3_shoreline.MIN_HOLE, s3_shoreline.MIN_GAP)
    s3_shoreline.OPEN_RADIUS = max(1, round(saved[0] / (res / 10)))
    s3_shoreline.MIN_HOLE = saved[1] / (res / 10) ** 2
    s3_shoreline.MIN_GAP = max(1, round(saved[2] / (res / 10) ** 2))
    try:
        lines = {}
        for idx_name, (water, field) in fields.items():
            lines[idx_name] = s3_shoreline.vectorize(water, a.rio.transform(), field)
    finally:
        s3_shoreline.OPEN_RADIUS, s3_shoreline.MIN_HOLE, s3_shoreline.MIN_GAP = saved
    (out / "shoreline").mkdir(parents=True, exist_ok=True)
    for idx_name, g in lines.items():
        if g is not None:
            gpd.GeoDataFrame({"year": [year], "water_index": [idx_name], "source": [name]}, geometry=[g], crs=C.CRS) \
                .to_file(out / "shoreline" / f"{year}_{idx_name}.json", driver="GeoJSON")
    return thr, {i: (g.length / 1000 if g is not None else None) for i, g in lines.items()}


def main(name, years):
    cfg = SOURCES[name]
    out = ALT / name
    (out / "composite").mkdir(parents=True, exist_ok=True)
    cat = pystac_client.Client.open(cfg["stac"], modifier=cfg["modifier"])
    log_f, thr_f = out / "scenes.json", out / "thresholds.json"
    log = json.loads(log_f.read_text()) if log_f.exists() else {}
    thrs = json.loads(thr_f.read_text()) if thr_f.exists() else {}
    for y in years:
        med, scenes = composite_year(name, cat, y)
        used = sum(s["used"] for s in scenes)
        log[str(y)] = {"scenes": scenes, "used": used, "low_confidence": used < 3}
        log_f.write_text(json.dumps(log, indent=1, ensure_ascii=False))
        if med is None:
            print(f"{name} {y}: KHÔNG có composite ({len(scenes)} cảnh)")
            continue
        med.rio.to_raster(out / "composite" / f"{y}.tif", compress="deflate")
        thr, km = water_and_shoreline(name, y, out)
        thrs[str(y)] = thr
        thr_f.write_text(json.dumps(thrs, indent=1))
        print(f"{name} {y}: {used}/{len(scenes)} cảnh; Otsu {thr}; bờ(km) { {k: (round(v, 2) if v else v) for k, v in km.items()} }"
              + ("  [ÍT CẢNH]" if used < 3 else ""), flush=True)


if __name__ == "__main__":
    if len(sys.argv) < 2 or sys.argv[1] not in SOURCES:
        raise SystemExit(f"Dùng: python alt_data.py {{{'|'.join(SOURCES)}}} [năm ...]")
    main(sys.argv[1], [int(a) for a in sys.argv[2:]] or C.YEARS)
