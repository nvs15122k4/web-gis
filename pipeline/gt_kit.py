"""Chuẩn bị bộ số hoá dữ liệu kiểm chứng (ground truth, Mục 2.4) để mở trong QGIS.

Tạo data/ground_truth/:
  ground_truth.gpkg   lớp `windows` (ô mẫu, đã điền sẵn) + lớp rỗng `water_<năm>` (Polygon) để người dùng số hoá;
                      trường `loai`: "song" (lòng sông Hậu — dùng suy ra đường bờ tham chiếu) | "khac" (ao, kênh, ruộng ngập)
  img/<năm>_swir_nir_green.tif  ảnh màu giả 8 bit (SWIR1-NIR-Green: nước xanh đen, đất/thực vật sáng) cắt quanh ô mẫu
Chỉ đưa ảnh vào bộ số hoá, KHÔNG đưa kết quả tự động (mặt nạ nước, đường bờ) để việc số hoá độc lập với pipeline.
Chạy lại sẽ KHÔNG ghi đè lớp water_<năm> đã số hoá.
"""
import geopandas as gpd
import numpy as np
import pyogrio
import rioxarray
from shapely.geometry import box

import config as C

GT = C.DATA / "ground_truth"
GPKG = GT / "ground_truth.gpkg"
YEARS = [2018, 2022, 2026]
WINDOW = 600  # m
MARGIN = 300  # m ảnh thừa quanh ô mẫu để nhìn bối cảnh
# (transect gần tâm ô, dạng bờ) — trải đều ~14 km bờ, đủ các dạng bờ khác nhau
SAMPLES = [
    ("T0030", "Bờ đông cù lao phía nam"),
    ("T0110", "Bờ thẳng hướng đông"),
    ("T0140", "Ngã ba Vàm Nao (bờ cong)"),
    ("T0205", "Đoạn có miệng kênh"),
    ("T0250", "Vùng sạt lở tham chiếu 2019–2020"),
]


def windows():
    tr = gpd.read_file(C.DATA / "transects.json").set_index("transect_code")
    shore = gpd.read_file(C.DATA / "shoreline" / f"{min(C.YEARS)}.json").geometry.iloc[0]
    rows = []
    for i, (code, kind) in enumerate(SAMPLES, 1):
        t = tr.loc[code].geometry
        x = t.intersection(shore)
        c = x if x.geom_type == "Point" else min(x.geoms, key=lambda p: p.distance(t.interpolate(0)))
        h = WINDOW / 2
        rows.append({"window_id": i, "name": f"W{i}", "kind": kind, "near_transect": code,
                     "geometry": box(c.x - h, c.y - h, c.x + h, c.y + h)})
    return gpd.GeoDataFrame(rows, crs=C.CRS)


def export_images(win):
    (GT / "img").mkdir(parents=True, exist_ok=True)
    minx, miny, maxx, maxy = win.total_bounds
    for y in YEARS:
        a = rioxarray.open_rasterio(C.DATA / "composite" / f"{y}.tif")
        a = a.rio.clip_box(minx - MARGIN, miny - MARGIN, maxx + MARGIN, maxy + MARGIN)
        rgb = a.isel(band=[2, 1, 0])  # SWIR1, NIR, Green
        # kéo giãn cố định 0–0,35 phản xạ (giống quicklook) để các năm so sánh được bằng mắt
        out = (np.clip(rgb.fillna(0) / 0.35, 0, 1) * 254 + 1).astype("uint8")
        out = out.where(rgb.notnull().all("band"), 0).astype("uint8").rio.write_nodata(0)
        out.rio.to_raster(GT / "img" / f"{y}_swir_nir_green.tif", compress="deflate")


def main():
    GT.mkdir(parents=True, exist_ok=True)
    win = windows()
    win.to_file(GPKG, layer="windows", driver="GPKG")
    existing = [l[0] for l in pyogrio.list_layers(GPKG)]
    for y in YEARS:
        name = f"water_{y}"
        if name in existing:
            continue
        empty = gpd.GeoDataFrame({"window_id": gpd.pd.Series(dtype="int32"), "loai": gpd.pd.Series(dtype="str"),
                                  "note": gpd.pd.Series(dtype="str")},
                                 geometry=gpd.GeoSeries([], crs=C.CRS))
        empty.to_file(GPKG, layer=name, driver="GPKG", geometry_type="Polygon")
    export_images(win)
    print(win.drop(columns="geometry").to_string(index=False))
    print(f"-> {GPKG}\n-> {GT / 'img'}")


if __name__ == "__main__":
    main()
