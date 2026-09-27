"""Bước 6 — Số hoá lớp sạt lở tham chiếu 2019–2020 tại Bình Mỹ (Mục 2.2) — CHỈ dùng để đối chiếu, không huấn luyện.

Nguồn chính thống (Báo An Giang, cổng TTĐT tỉnh An Giang):
  - 27/07/2019: nứt mặt QL91 Km 89+272 – 89+336, ấp Bình Tân; 31/07–01/08/2019 sạt 85 m; 20/08/2019 sập thêm 30 m.
  - Vùng cảnh báo dọc sông Hậu từ vàm xáng Cây Dương đến bến phà Năng Gù.
  - 23/05/2020: nứt tại Km 89+076; 25/05/2020 UBND tỉnh công bố tình huống khẩn cấp đoạn 2.000 m.
Lý trình Km không có toạ độ công khai, nên chỉ số hoá VÙNG cảnh báo: đoạn đường bờ 2018 nằm giữa hình chiếu của
cầu Cây Dương (miệng vàm) và bến phà Năng Gù lên bờ phía Bình Mỹ. Vị trí điểm sạt lở cụ thể cần số hoá bổ sung
khi có toạ độ thực địa (ghi rõ trong báo cáo).
Đầu ra: output/reference.geojson (EPSG:4326).
"""
import json

import geopandas as gpd
from shapely.geometry import Point
from shapely.ops import substring

import config as C

# (lon, lat) theo OpenStreetMap
CAY_DUONG = (105.2645061, 10.5567828)   # cầu Cây Dương — miệng vàm xáng Cây Dương
NANG_GU = (105.2956148, 10.5565224)     # bến phà Năng Gù (bờ đối diện) — chiếu vuông góc sang bờ Bình Mỹ

EVENTS = [
    {"event_date": "2019-08-01", "danger_level": "dac_biet_nguy_hiem", "source": "Báo An Giang, 01/08/2019; 20/08/2019",
     "description": "Vùng cảnh báo sạt lở QL91 ấp Bình Tân (sạt 85 m đêm 31/7–1/8/2019, sập thêm 30 m ngày 20/8/2019; "
                    "Km 89+272–89+336). Vị trí trong vùng chưa xác định chính xác."},
    {"event_date": "2020-05-25", "danger_level": "dac_biet_nguy_hiem",
     "source": "UBND tỉnh An Giang, QĐ tình huống khẩn cấp 25/05/2020 (Báo An Giang 27/05/2020)",
     "description": "Tình huống khẩn cấp nguy cơ sạt lở nghiêm trọng QL91 qua xã Bình Mỹ, dài 2.000 m "
                    "(vết nứt Km 89+076 ngày 23/5/2020)."},
]


def main():
    shore = gpd.read_file(C.DATA / "shoreline" / f"{min(C.YEARS)}.geojson").geometry.iloc[0]
    line = max(shore.geoms, key=lambda g: g.length)
    pts = gpd.GeoSeries([Point(CAY_DUONG), Point(NANG_GU)], crs=4326).to_crs(C.CRS)
    a, b = sorted(line.project(p) for p in pts)
    zone = substring(line, a, b)
    feats = gpd.GeoDataFrame([{**e, "length_m": round(zone.length), "location": "Ấp Bình Tân, xã Bình Mỹ, An Giang",
                               "geometry": zone} for e in EVENTS], crs=C.CRS).to_crs(4326)
    C.OUTPUT.mkdir(parents=True, exist_ok=True)
    (C.OUTPUT / "reference.geojson").write_text(feats.to_json(ensure_ascii=False))
    print(f"Vùng cảnh báo tham chiếu dài {zone.length:.0f} m dọc bờ")
    write_aoi()


def write_aoi():
    """AOI cho web (Mục 2.2): ranh giới xã + vùng nghiên cứu = dải bờ sông mà transect phủ tới."""
    tr = gpd.read_file(C.DATA / "transects.geojson").to_crs(C.CRS)
    study = tr.buffer(C.TRANSECT_SPACING).union_all().simplify(20)
    commune = gpd.read_file(C.COMMUNE_FILE).to_crs(C.CRS).geometry.iloc[0].simplify(10)
    aoi = gpd.GeoDataFrame([
        {"name": "Vùng nghiên cứu — đoạn bờ sông Hậu thuộc xã Bình Mỹ", "role": "study_area", "geometry": study},
        {"name": "Ranh giới xã Bình Mỹ (OpenStreetMap, sau sáp nhập 2025)", "role": "commune", "geometry": commune},
    ], crs=C.CRS).to_crs(4326)
    (C.OUTPUT / "aoi.geojson").write_text(aoi.to_json(ensure_ascii=False))


if __name__ == "__main__":
    main()
