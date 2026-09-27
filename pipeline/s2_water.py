"""Bước 2 — Tính chỉ số nước NDWI, MNDWI, AWEI và phân loại nước/đất bằng ngưỡng Otsu (Mục 2.3).

Otsu tính trên histogram chỉ số trong vùng phân tích (ranh giới xã + vùng đệm để lấy trọn lòng sông).
Đầu ra: data/water/<năm>_<chỉ số>.tif (uint8: 1 nước, 0 đất, 255 không dữ liệu) và data/water/thresholds.json.
"""
import json
import sys

import geopandas as gpd
import numpy as np
import rioxarray
from rasterio.features import geometry_mask
from skimage.filters import threshold_otsu

import config as C

OUT = C.DATA / "water"
ANALYSIS_BUFFER = 1000  # m quanh ranh giới xã — ranh giới xã chạy giữa dòng nên cần đệm để có đủ hai phía


def indices(a):
    g, nir, sw1, sw2 = (a.sel(band=i).values for i in (1, 2, 3, 4))
    with np.errstate(divide="ignore", invalid="ignore"):
        return {
            "ndwi": (g - nir) / (g + nir),                              # McFeeters (1996)
            "mndwi": (g - sw1) / (g + sw1),                             # Xu (2006)
            "awei": 4 * (g - sw1) - (0.25 * nir + 2.75 * sw2),          # Feyisa et al. (2014), AWEI_nsh
        }


def analysis_mask(a):
    zone = gpd.read_file(C.COMMUNE_FILE).to_crs(C.CRS).buffer(ANALYSIS_BUFFER)
    return geometry_mask(zone.geometry, out_shape=a.shape[1:], transform=a.rio.transform(), invert=True)


def main(years):
    OUT.mkdir(parents=True, exist_ok=True)
    thr_file = OUT / "thresholds.json"
    thresholds = json.loads(thr_file.read_text()) if thr_file.exists() else {}
    for y in years:
        src = C.DATA / "composite" / f"{y}.tif"
        if not src.exists():
            print(f"{y}: bỏ qua (không có composite)")
            continue
        a = rioxarray.open_rasterio(src)
        zone = analysis_mask(a)
        thresholds[str(y)] = {}
        for name, idx in indices(a).items():
            valid = zone & np.isfinite(idx)
            t = float(threshold_otsu(idx[valid]))
            water = np.full(idx.shape, 255, np.uint8)
            water[np.isfinite(idx)] = (idx[np.isfinite(idx)] > t).astype(np.uint8)
            out = a.isel(band=0).copy(data=water).rio.write_nodata(255)
            out.rio.to_raster(OUT / f"{y}_{name}.tif", compress="deflate")
            thresholds[str(y)][name] = round(t, 4)
            print(f"{y} {name:5s} Otsu={t:+.4f}  nước={water[valid].mean():.1%} vùng phân tích")
    thr_file.write_text(json.dumps(thresholds, indent=1))


if __name__ == "__main__":
    main([int(a) for a in sys.argv[1:]] or C.YEARS)
