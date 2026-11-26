"""Bước 9 — Đối chiếu hotspot lùi bờ mạnh với vùng sạt lở tham chiếu 2019–2020 (Mục 2.4). CHỈ kiểm chứng, không huấn luyện.

Hai phép đo, đều trong EPSG:32648 (mét):
  1. Khoảng cách từ vùng cảnh báo tới hotspot gần nhất; "khớp" khi ≤ config.MATCH_RADIUS (m).
  2. Thống kê các transect nằm trong vùng cảnh báo (cách ≤ config.ZONE_BUFFER m): số lượng, phân bố nhóm biến động, LRR trung bình,
     tỉ lệ transect lùi bờ (LRR < 0) và lùi bờ có ý nghĩa thống kê. Phép đo này không phụ thuộc việc có hotspot hay không,
     nên cho biết ảnh viễn thám có thấy xu hướng lùi bờ trong vùng cảnh báo hay không dù ngưỡng "lùi mạnh" chưa vượt.
Vùng cảnh báo là đoạn dài (không phải điểm sạt) nên kết quả chỉ có giá trị đối chiếu thô, không đánh giá độ chính xác vị trí.
Đầu ra: output/validation.json.
"""
import json
from collections import Counter

import geopandas as gpd
import numpy as np

import config as C


def match_events(events, hotspots, transects, radius=None, buffer=None):
    """events/hotspots/transects: GeoDataFrame trong CRS mét. transects có cột `stats` (dict). Trả về list dict, mỗi sự kiện một dòng."""
    radius = C.MATCH_RADIUS if radius is None else radius
    buffer = C.ZONE_BUFFER if buffer is None else buffer
    hs = hotspots.geometry.union_all() if len(hotspots) else None
    out = []
    for _, ev in events.iterrows():
        d = float(ev.geometry.distance(hs)) if hs is not None else None
        near = transects[transects.geometry.distance(ev.geometry) <= buffer]
        stats = [s for s in near["stats"]]
        lrr = np.array([s["lrr_m_per_year"] for s in stats if s.get("lrr_m_per_year") is not None])
        out.append({
            "event_date": str(ev["event_date"])[:10], "zone_length_m": round(float(ev.geometry.length)),
            "nearest_hotspot_m": None if d is None else round(d),
            "within_radius": bool(d is not None and d <= radius), "radius_m": radius,
            "transects_in_zone": int(len(stats)),
            "classes": dict(Counter(s["change_class"] for s in stats)),
            "mean_lrr_m_per_year": round(float(lrr.mean()), 3) if lrr.size else None,
            "share_eroding": round(float((lrr < 0).mean()), 3) if lrr.size else None,
            "share_significant_erosion": round(float(np.mean([bool(s["is_significant"]) and s["lrr_m_per_year"] < 0
                                                              for s in stats])), 3) if stats else None,
        })
    return out


def main():
    events = gpd.read_file(C.OUTPUT / "reference.json").to_crs(C.CRS)
    hotspots = gpd.read_file(C.OUTPUT / "hotspots.json").to_crs(C.CRS)
    raw = json.loads((C.OUTPUT / "transects.json").read_text())
    geo = gpd.read_file(C.OUTPUT / "transects.json").to_crs(C.CRS)
    tr = geo.assign(stats=[f["properties"]["stats"] for f in raw["features"]])
    rows = match_events(events, hotspots, tr)
    out = {"events": rows, "matched": sum(r["within_radius"] for r in rows), "total": len(rows),
           "note": "Vùng cảnh báo là đoạn bờ dài, không phải điểm sạt; dữ liệu chỉ để kiểm chứng, không dùng huấn luyện."}
    (C.OUTPUT / "validation.json").write_text(json.dumps(out, indent=1, ensure_ascii=False))
    print(json.dumps(out, indent=1, ensure_ascii=False))


if __name__ == "__main__":
    main()
