"""Bước 1 — Thu thập ảnh Sentinel-2 L2A mùa khô, lọc mây/bóng mây bằng SCL, tạo composite trung vị mỗi năm.

Đầu ra: data/composite/<năm>.tif (4 kênh phản xạ B03,B08,B11,B12, float32 0–1) + data/composite/scenes.json.
"""
import json
import sys

import numpy as np
import planetary_computer as pc
import pystac_client
import rioxarray  # noqa: F401  (đăng ký accessor .rio)
from odc.stac import load

import config as C

OUT = C.DATA / "composite"


def harmonize(ds, items):
    """Từ processing baseline 04.00 (25/01/2022) ESA cộng offset 1000 vào DN L2A — trừ lại để các năm so sánh được."""
    offset = np.array([1000 if float(i.properties["s2:processing_baseline"]) >= 4.0 else 0 for i in items])
    for b in ["B03", "B08", "B11", "B12"]:
        v = ds[b].astype("float32")
        v = v.where(v > 0)  # 0 = no-data
        ds[b] = (v - offset[:, None, None]) / 10000.0
    return ds


def composite_year(cat, year):
    items = list(cat.search(
        collections=[C.COLLECTION], bbox=C.BBOX,
        datetime=f"{year}-{C.SEASON[0]}/{year}-{C.SEASON[1]}",
    ).items())
    items = [i for i in items if i.properties["eo:cloud_cover"] < C.PREFILTER_CLOUD]
    if not items:
        return None, []
    items.sort(key=lambda i: i.datetime)
    ds = load(items, bands=C.BANDS, bbox=C.BBOX, crs=C.CRS, resolution=C.RESOLUTION,
              groupby="solar_day", chunks={},
              resampling={"SCL": "nearest", "*": "bilinear"})  # SWIR 20 m -> 10 m nội suy song tuyến
    ds = ds.compute()
    # groupby solar_day có thể gộp cảnh — lấy lại item đại diện theo ngày để biết baseline
    by_day = {}
    for i in items:
        by_day.setdefault(i.datetime.date(), i)
    day_items = [by_day[np.datetime64(t, "D").astype(object)] for t in ds.time.values]

    masked = ds.SCL.isin(C.SCL_MASK)
    frac = masked.mean(dim=("x", "y")).values
    keep = frac <= C.MAX_MASKED_AOI
    scenes = [{"date": str(np.datetime64(t, "D")), "masked_fraction": round(float(f), 3), "used": bool(k),
               "processing_baseline": it.properties["s2:processing_baseline"]}
              for t, f, k, it in zip(ds.time.values, frac, keep, day_items)]
    if not keep.any():
        return None, scenes

    ds = ds.isel(time=np.where(keep)[0])
    ds = harmonize(ds, [it for it, k in zip(day_items, keep) if k])
    clear = ~ds.SCL.isin(C.SCL_MASK)
    refl = ds[["B03", "B08", "B11", "B12"]].where(clear)
    med = refl.median(dim="time", skipna=True).to_array("band").astype("float32")
    med = med.rio.write_crs(C.CRS)
    return med, scenes


def main(years):
    OUT.mkdir(parents=True, exist_ok=True)
    cat = pystac_client.Client.open(C.STAC_URL, modifier=pc.sign_inplace)
    log_file = OUT / "scenes.json"
    log = json.loads(log_file.read_text()) if log_file.exists() else {}
    for y in years:
        med, scenes = composite_year(cat, y)
        used = sum(s["used"] for s in scenes)
        log[str(y)] = {"scenes": scenes, "used": used,
                       "source_image_date": next((s["date"] for s in reversed(scenes) if s["used"]), None)}
        if med is None:
            print(f"{y}: KHÔNG có composite hợp lệ ({len(scenes)} cảnh, đều bị mây che quá ngưỡng)")
            continue
        med.rio.to_raster(OUT / f"{y}.tif", compress="deflate")
        print(f"{y}: composite từ {used}/{len(scenes)} cảnh -> {OUT / f'{y}.tif'}")
        log_file.write_text(json.dumps(log, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main([int(a) for a in sys.argv[1:]] or C.YEARS)
