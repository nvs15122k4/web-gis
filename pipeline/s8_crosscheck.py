"""Bước 8 — Đối chiếu kết quả với sản phẩm phủ bề mặt độc lập (bổ sung cho Mục 2.4, KHÔNG thay ground truth).

Nguồn tham chiếu (Microsoft Planetary Computer, không cần tài khoản):
  - ESA WorldCover 10 m v100/v200 (Zanaga et al., 2021; 2022), năm 2020 và 2021 — lớp 80 "Permanent water bodies".
  - Impact Observatory 10 m Annual LULC v02 (Karra et al., 2021), năm 2018–2023 — lớp 1 "Water".
Hai sản phẩm do nhóm khác sản xuất bằng thuật toán khác (học máy, ảnh cả năm), nên độc lập với ngưỡng Otsu của
pipeline. Hạn chế cần nêu trong báo cáo: là ảnh tổng hợp cả năm (gồm mùa lũ) chứ không chỉ tháng 1–3, độ chính xác
tổng thể công bố ~75–85% — đây là phép đối chiếu chéo, không phải bộ kiểm chứng giải đoán trực quan.
  - Precision/Recall/F1/IoU: pixel nước của từng chỉ số so với pixel nước tham chiếu, trong ranh giới xã + đệm 1 km
    (đúng vùng tính ngưỡng Otsu ở bước 2).
  - Sai lệch đường bờ: đường bờ tham chiếu vector hoá từ mặt nạ nước tham chiếu bằng đúng thủ tục bước 3; đo chênh
    khoảng cách từ gốc transect tới hai đường bờ (mean, mean |.|, trung vị, RMSE, số transect lệch > 50 m).
    Dương = đường bờ pipeline nằm phía sông so với tham chiếu.
Đầu ra: output/crosscheck.json, data/crosscheck/<nguồn>_<năm>.tif|.json.
"""
import json

import geopandas as gpd
import numpy as np
import planetary_computer as pc
import pystac_client
import rioxarray
from odc.stac import load
from rasterio.enums import Resampling
from rasterio.features import geometry_mask

import config as C
import s2_water
import s3_shoreline
from s7_evaluate import nearest_hit, pixel_metrics

OUT = C.DATA / "crosscheck"
SOURCES = {
    # tên: (collection, asset, mã lớp nước, năm có dữ liệu)
    "worldcover": ("esa-worldcover", "map", 80, [2020, 2021]),
    "io_lulc": ("io-lulc-annual-v02", "data", 1, list(range(2018, 2024))),
}


def reference_mask(cat, collection, asset, water_class, year, like):
    items = list(cat.search(collections=[collection], bbox=C.BBOX, datetime=f"{year}-01-01/{year}-12-31").items())
    if not items:
        return None
    ds = load(items, bands=[asset], bbox=C.BBOX, crs=C.CRS, resolution=C.RESOLUTION, groupby="solar_day",
              resampling="nearest").isel(time=0)
    a = ds[asset].rio.write_crs(C.CRS).rio.reproject_match(like, resampling=Resampling.nearest)
    nodata = a.rio.nodata
    out = (a.values == water_class).astype(np.uint8)
    if nodata is not None:
        out[a.values == nodata] = 255
    return like.copy(data=out).rio.write_nodata(255)


def shoreline_offsets(auto, ref, transects):
    diffs = []
    for t in transects:
        da, dr = nearest_hit(t, auto), nearest_hit(t, ref)
        if da is not None and dr is not None:
            diffs.append(da - dr)
    if not diffs:
        return None
    d = np.array(diffs)
    return {"n_transects": len(d), "mean_m": round(float(d.mean()), 2),
            "mean_abs_m": round(float(np.abs(d).mean()), 2), "median_m": round(float(np.median(d)), 2),
            "rmse_m": round(float(np.sqrt((d ** 2).mean())), 2), "n_over_50m": int((np.abs(d) > 50).sum())}


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    cat = pystac_client.Client.open(C.STAC_URL, modifier=pc.sign_inplace)
    transects = list(gpd.read_file(C.DATA / "transects.json").geometry)
    report = {}
    for src, (collection, asset, water_class, years) in SOURCES.items():
        report[src] = {}
        for y in years:
            like = rioxarray.open_rasterio(C.DATA / "water" / f"{y}_{C.DEFAULT_INDEX}.tif").isel(band=0)
            ref = reference_mask(cat, collection, asset, water_class, y, like)
            if ref is None:
                print(f"{src} {y}: không có dữ liệu")
                continue
            ref.rio.to_raster(OUT / f"{src}_{y}.tif", compress="deflate")
            zone = s2_water.analysis_mask(like.expand_dims("band"))
            r = {}
            for idx in C.WATER_INDICES:
                a = rioxarray.open_rasterio(C.DATA / "water" / f"{y}_{idx}.tif").isel(band=0).values
                valid = zone & (a != 255) & (ref.values != 255)
                r[idx] = pixel_metrics(a[valid] == 1, ref.values[valid] == 1)
            ref_line = s3_shoreline.vectorize(ref.values.copy(), ref.rio.transform())
            auto_file = C.DATA / "shoreline" / f"{y}.json"
            if ref_line is not None and auto_file.exists():
                gpd.GeoDataFrame({"source": [src], "year": [y]}, geometry=[ref_line], crs=C.CRS) \
                    .to_file(OUT / f"{src}_{y}.json", driver="GeoJSON")
                auto = gpd.read_file(auto_file).geometry.union_all()
                r["shoreline_offset"] = shoreline_offsets(auto, ref_line, transects)
            report[src][y] = r
            print(f"{src} {y}: " + "  ".join(f"{i} F1={r[i]['f1']:.3f}" for i in C.WATER_INDICES)
                  + (f"  RMSE bờ={r['shoreline_offset']['rmse_m']} m" if r.get("shoreline_offset") else ""))
    mean_f1 = {i: round(float(np.mean([v[i]["f1"] for s in report.values() for v in s.values()])), 4)
               for i in C.WATER_INDICES}
    out = {"by_source": report, "mean_f1_all": mean_f1, "best_index_by_mean_f1": max(mean_f1, key=mean_f1.get),
           "note": "Đối chiếu chéo với sản phẩm phủ bề mặt độc lập (ảnh cả năm, độ chính xác ~75–85%). "
                   "Không thay ground truth số hoá thủ công Mục 2.4; không dùng để điền U_CLASSIFY."}
    (C.OUTPUT / "crosscheck.json").write_text(json.dumps(out, indent=1, ensure_ascii=False))
    print(json.dumps({k: out[k] for k in ("mean_f1_all", "best_index_by_mean_f1")}, ensure_ascii=False))


if __name__ == "__main__":
    main()
