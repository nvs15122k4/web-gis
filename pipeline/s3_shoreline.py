"""Bước 3 — Vector hoá đường bờ từ raster nước/đất (Mục 2.3).

Làm sạch mặt nạ: mở hình thái học (bỏ kênh rạch hẹp để đường bờ cắt ngang miệng kênh, như cách số hoá DSAS),
giữ vùng nước liên thông lớn nhất (sông Hậu + Vàm Nao), lấp lỗ nhỏ (ghe tàu, bèo). Dò biên bằng contour mức 0,5
(độ chính xác dưới pixel), đơn giản hoá, rồi chỉ giữ đoạn bờ nằm trong ranh giới xã.
Đầu ra: data/shoreline/<năm>.geojson (EPSG:32648, MultiLineString).
"""
import sys

import geopandas as gpd
import numpy as np
import rioxarray
from scipy import ndimage
from rasterio.features import shapes
from shapely.geometry import LineString, MultiLineString, shape
from shapely.ops import linemerge, unary_union
from skimage.measure import find_contours
from skimage.morphology import disk

import config as C

OUT = C.DATA / "shoreline"
OPEN_RADIUS = 5            # px (50 m) — kênh rạch hẹp hơn ~100 m bị loại
MIN_HOLE = 50_000 / 100    # px — lấp lỗ < 5 ha trong lòng sông
MIN_PIECE = 200            # m — bỏ mảnh đường bờ quá ngắn
MIN_GAP = 25               # px — cụm no-data từ 0,25 ha trở lên coi là vùng thiếu dữ liệu thật
BANK_ZONE = 100            # m — ranh giới xã chạy giữa dòng; nới nhẹ để không cắt mất đoạn bờ sát ranh


def clean_water(water):
    w = ndimage.binary_opening(water, structure=disk(OPEN_RADIUS))
    lab, n = ndimage.label(w)
    if n == 0:
        return w
    sizes = ndimage.sum(w, lab, range(1, n + 1))
    w = lab == (np.argmax(sizes) + 1)
    holes = ndimage.binary_fill_holes(w) & ~w
    hl, hn = ndimage.label(holes)
    if hn:
        hs = ndimage.sum(holes, hl, range(1, hn + 1))
        w |= np.isin(hl, np.where(hs < MIN_HOLE)[0] + 1)
    return w


def fill_small_gaps(raw):
    """Lấp cụm no-data nhỏ (mây/bão hoà sót vài pixel) bằng giá trị pixel hợp lệ gần nhất; trả về mặt nạ cụm lớn."""
    nod = raw == 255
    if not nod.any():
        return raw, nod
    lab, n = ndimage.label(nod)
    sizes = ndimage.sum(nod, lab, range(1, n + 1))
    big = np.isin(lab, np.where(sizes >= MIN_GAP)[0] + 1)
    idx = ndimage.distance_transform_edt(nod, return_distances=False, return_indices=True)
    filled = raw[tuple(idx)]
    filled[big] = 0
    return filled, big


def extract(year, index):
    a = rioxarray.open_rasterio(C.DATA / "water" / f"{year}_{index}.tif").isel(band=0)
    return vectorize(a.values, a.rio.transform())


def vectorize(raw, tr):
    """Raster nước uint8 (1 nước, 0 đất, 255 không dữ liệu) -> MultiLineString đường bờ trong ranh giới xã."""
    raw, big_gaps = fill_small_gaps(raw)
    water = clean_water(raw == 1)
    lines = []
    for c in find_contours(water.astype(float), 0.5):
        rows, cols = c[:, 0], c[:, 1]
        xs, ys = tr * (cols + 0.5, rows + 0.5)
        if len(xs) > 1:
            lines.append(LineString(np.column_stack([xs, ys])))
    zone = gpd.read_file(C.COMMUNE_FILE).to_crs(C.CRS).buffer(BANK_ZONE).union_all()
    geom = MultiLineString(lines).intersection(zone)
    if big_gaps.any():  # đoạn bờ đi qua vùng không dữ liệu lớn là biên giả -> cắt bỏ
        gaps = unary_union([shape(g) for g, v in shapes(big_gaps.astype(np.uint8), mask=big_gaps, transform=tr)])
        geom = geom.difference(gaps.buffer(2 * C.RESOLUTION))
    geom = linemerge(geom) if geom.geom_type == "MultiLineString" else geom
    parts = [g for g in getattr(geom, "geoms", [geom]) if g.length >= MIN_PIECE]
    parts = [p.simplify(C.SMOOTH_TOLERANCE) for p in parts]
    return MultiLineString(parts) if parts else None


def main(years, index=C.DEFAULT_INDEX):
    OUT.mkdir(parents=True, exist_ok=True)
    for y in years:
        if not (C.DATA / "water" / f"{y}_{index}.tif").exists():
            print(f"{y}: bỏ qua (không có raster nước)")
            continue
        g = extract(y, index)
        if g is None:
            print(f"{y}: không trích được đường bờ")
            continue
        gpd.GeoDataFrame({"year": [y], "water_index": [index]}, geometry=[g], crs=C.CRS) \
            .to_file(OUT / f"{y}.geojson", driver="GeoJSON")
        print(f"{y}: {len(g.geoms)} đoạn, tổng {g.length / 1000:.2f} km")


if __name__ == "__main__":
    main([int(a) for a in sys.argv[1:]] or C.YEARS)
