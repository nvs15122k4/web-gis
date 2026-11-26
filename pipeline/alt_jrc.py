"""Đối chiếu mặt nước bản chính / Landsat / Sentinel-2 C1 với JRC Global Surface Water v1.4 (Pekel et al., 2016).

JRC `occurrence` = % số lần quan sát thấy nước trong 1984–2020 (Landsat 30 m). Đây là sản phẩm tổng hợp NHIỀU NĂM nên
không dùng để đo đường bờ từng năm; chỉ kiểm tra (a) lòng sông chính của pipeline có khớp vùng "nước thường xuyên"
(occurrence >= 75%) không, (b) vùng pipeline gọi là nước nhưng JRC thấy nước < 25% (đất ngập theo mùa/bãi bồi/lỗi mây).
Đầu ra: output/alt_jrc.json, data/alt/jrc/occurrence.tif (cắt theo vùng, lưới 10 m của bản chính).
"""
import json

import numpy as np
import planetary_computer as pc
import pystac_client
import rioxarray
from odc.stac import load
from rasterio.enums import Resampling

import config as C
import s2_water
from s7_evaluate import pixel_metrics

OUT = C.DATA / "alt" / "jrc"


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    cat = pystac_client.Client.open(C.STAC_URL, modifier=pc.sign_inplace)
    items = list(cat.search(collections=["jrc-gsw"], bbox=C.BBOX).items())
    ds = load(items, bands=["occurrence"], bbox=C.BBOX, crs=C.CRS, resolution=30, chunks={}).compute()
    occ = ds["occurrence"].isel(time=0).rio.write_crs(C.CRS)
    occ.rio.to_raster(OUT / "occurrence.tif", compress="deflate")
    report = {"item": [i.id for i in items], "note": "occurrence 0–100 (%); 255 = không dữ liệu"}
    for name, path in (("main", lambda y: C.DATA / "water" / f"{y}_{C.DEFAULT_INDEX}.tif"),
                       ("landsat", lambda y: C.DATA / "alt" / "landsat" / "water" / f"{y}_{C.DEFAULT_INDEX}.tif"),
                       ("s2c1", lambda y: C.DATA / "alt" / "s2c1" / "water" / f"{y}_{C.DEFAULT_INDEX}.tif")):
        report[name] = {}
        for y in (2018, 2019, 2020):
            f = path(y)
            if not f.exists():
                continue
            w = rioxarray.open_rasterio(f).isel(band=0)
            o = occ.rio.reproject_match(w, resampling=Resampling.nearest).values
            zone = s2_water.analysis_mask(w.expand_dims("band"))
            valid = zone & (w.values != 255) & (o != 255)
            pred, ref = w.values[valid] == 1, o[valid] >= 75
            m = pixel_metrics(pred, ref)
            m["water_but_jrc_lt25_share"] = round(float((pred & (o[valid] < 25)).sum() / max(pred.sum(), 1)), 4)
            report[name][y] = m
            print(name, y, m)
    (C.OUTPUT / "alt_jrc.json").write_text(json.dumps(report, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
