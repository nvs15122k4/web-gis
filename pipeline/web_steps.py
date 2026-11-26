"""Sinh ảnh minh hoạ cho trang Phương pháp (/gioi-thieu) từ dữ liệu pipeline đã có.

Chỉ vẽ lại dữ liệu trung gian (composite, MNDWI, mặt nạ nước, đường bờ, transect), không tính lại chỉ số.
    .venv/bin/python web_steps.py [--data <thư mục data>] [--out <thư mục ảnh>] [--bo-sung]
`--bo-sung` chỉ vẽ các hình chi tiết thêm (lọc cảnh, băng phổ, Otsu, trước/sau làm sạch, phóng to transect, độ chính xác, bất định)
và ghi output/web_steps.json (số liệu đi kèm các hình, để test đối chiếu với văn bản trang).
"""
import argparse, json
from pathlib import Path
import matplotlib; matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np, rioxarray, geopandas as gpd
from matplotlib.colors import ListedColormap
from PIL import Image
from skimage.filters import threshold_otsu
from shapely.geometry import Point
import config as C
import s2_water, s3_shoreline, s4_transects

YEAR = 2026
CLASS_COLOR = {"accretion": "#1e88e5", "stable": "#9e9e9e", "mild_erosion": "#fb8c00", "severe_erosion": "#d32f2f"}


def save(fig, out, name):
    tmp = out / f"{name}.png"
    fig.savefig(tmp, dpi=110, bbox_inches="tight", pad_inches=0.05)
    plt.close(fig)
    Image.open(tmp).convert("RGB").save(out / f"{name}.webp", quality=82, method=6)
    tmp.unlink()


def main(data, out):
    out.mkdir(parents=True, exist_ok=True)
    a = rioxarray.open_rasterio(data / "composite" / f"{YEAR}.tif").astype("float32")
    g, nir, swir = a[0].values, a[1].values, a[2].values
    b = a.rio.bounds(); ext = [b[0], b[2], b[1], b[3]]
    rgb = np.clip(np.dstack([swir, nir, g]) / 0.35, 0, 1)
    commune = gpd.read_file(data / "ref" / "binhmy_osm.json").to_crs(C.CRS)
    tr = gpd.read_file(C.OUTPUT / "transects.json").to_crs(C.CRS)
    tr["cls"] = tr["stats"].map(lambda s: s["change_class"]) if "stats" in tr else tr["change_class"]

    def base(title, crop=None, fs=(9, 6.2)):
        fig, ax = plt.subplots(figsize=fs)
        ax.imshow(rgb, extent=ext)
        commune.boundary.plot(ax=ax, color="yellow", lw=1, ls="--")
        ax.set_axis_off()
        if crop: ax.set_xlim(crop[0], crop[2]); ax.set_ylim(crop[1], crop[3])
        return fig, ax

    # 1. Đầu vào: composite mùa khô, ảnh giả màu
    fig, ax = base(f"Bước 1 · Composite mùa khô {YEAR} (giả màu SWIR1 · NIR · Xanh lục), ranh giới xã nét đứt")
    save(fig, out, "b1-composite")

    # 2. MNDWI + ngưỡng Otsu
    thr = json.loads((data / "water" / "thresholds.json").read_text())[str(YEAR)]["mndwi"]
    with np.errstate(divide="ignore", invalid="ignore"):
        mndwi = (g - swir) / (g + swir)
    fig, ax = plt.subplots(figsize=(9, 6.2))
    im = ax.imshow(mndwi, extent=ext, cmap="RdYlBu", vmin=-0.8, vmax=0.8)
    ax.set_axis_off(); fig.colorbar(im, ax=ax, shrink=0.7, label="MNDWI")
    save(fig, out, "b2-mndwi")

    # 3. Mặt nạ nước
    w = rioxarray.open_rasterio(data / "water" / f"{YEAR}_mndwi.tif")[0].values
    fig, ax = plt.subplots(figsize=(9, 6.2))
    ax.imshow(np.where(w == 255, np.nan, w), extent=ext, cmap=ListedColormap(["#d9c9a3", "#1e5fa8"]), vmin=0, vmax=1, interpolation="nearest")
    commune.boundary.plot(ax=ax, color="black", lw=0.8, ls="--"); ax.set_axis_off()
    save(fig, out, "b3-mat-na-nuoc")

    # vùng phóng to quanh hotspot
    hs = tr[tr["transect_code"] == "T0094"].geometry.union_all().centroid   # transect được phân tích chi tiết ở bước 7
    r = 1300; crop = (hs.x - r, hs.y - r * 0.7, hs.x + r, hs.y + r * 0.7)

    # 4. Đường bờ 2018→2026
    fig, ax = base("Bước 4 · Đường bờ 2018–2026 (mỗi năm một màu), vùng phóng to quanh điểm nóng", crop, (9, 5.6))
    cmap = plt.get_cmap("viridis")
    for i, y in enumerate(C.YEARS):
        gpd.read_file(data / "shoreline" / f"{y}.json").to_crs(C.CRS).plot(ax=ax, color=cmap(i / 8), lw=1.4, label=str(y))
    ax.legend(ncol=3, fontsize=8, loc="lower left", title="Năm", framealpha=0.85)
    save(fig, out, "b4-duong-bo")

    # 5. Baseline + transect
    bl = gpd.read_file(C.OUTPUT / "baseline.json").to_crs(C.CRS)
    fig, ax = base("Bước 5 · Baseline (đỏ nét đứt) và transect vuông góc cách 50 m, tô theo mức biến động", crop, (9, 5.6))
    for k, c in CLASS_COLOR.items():
        sub = tr[tr["cls"] == k]
        if len(sub): sub.plot(ax=ax, color=c, lw=1.2)
    bl.plot(ax=ax, color="red", lw=1.5, ls="--")
    save(fig, out, "b5-transect")

    # 6. Chuỗi khoảng cách của transect điểm nóng + hồi quy
    row = tr[tr["transect_code"] == "T0094"].iloc[0]
    props = json.loads(json.dumps(row.drop("geometry").to_dict(), default=str))
    d = props["distances"]; st = props["stats"]
    d = d if isinstance(d, dict) else json.loads(d); st = st if isinstance(st, dict) else json.loads(st)
    ys = np.array([int(k) for k, v in d.items() if v is not None]); ds = np.array([v for v in d.values() if v is not None], float)
    fig, ax = plt.subplots(figsize=(7, 4))
    ax.plot(ys, ds, "o-", color="#1565c0", label="Khoảng cách baseline → đường bờ")
    ax.plot(ys, np.polyval(np.polyfit(ys, ds, 1), ys), "--", color="#d32f2f", label=("Hồi quy tuyến tính (LRR = " + f"{st['lrr_m_per_year']:.2f}".replace("-", "\u2212").replace(".", ",") + " m/năm)"))
    ax.set_xlabel("Năm"); ax.set_ylabel("Khoảng cách (m)"); ax.grid(alpha=.3); ax.legend(fontsize=8)
    save(fig, out, "b6-chuoi-nam")
    print("xong:", sorted(p.name for p in out.glob("*.webp")))


def extra(data, out):
    """Các hình chi tiết thêm. Mọi số trên hình/ghi chú đọc từ file dữ liệu hoặc tính lại bằng đúng hàm của pipeline."""
    out.mkdir(parents=True, exist_ok=True)
    facts = {}
    vn = lambda x, nd=2: f"{x:.{nd}f}".replace("-", "\u2212").replace(".", ",")
    a = rioxarray.open_rasterio(data / "composite" / f"{YEAR}.tif").astype("float32")
    b = a.rio.bounds(); ext = [b[0], b[2], b[1], b[3]]
    commune = gpd.read_file(data / "ref" / "binhmy_osm.json").to_crs(C.CRS)
    tr = gpd.read_file(C.OUTPUT / "transects.json").to_crs(C.CRS)
    tr["cls"] = tr["stats"].map(lambda s: s["change_class"])
    hs = tr[tr["transect_code"] == "T0094"].geometry.union_all().centroid   # transect được phân tích chi tiết ở bước 7
    rgb = np.clip(np.dstack([a[2].values, a[1].values, a[0].values]) / 0.35, 0, 1)

    # A. Lọc cảnh: mỗi cảnh một điểm, trục x = ngày trong mùa, tô theo tỉ lệ pixel bị che; X đỏ = cảnh bị loại
    scenes = json.loads((data / "composite" / "scenes.json").read_text())
    fig, ax = plt.subplots(figsize=(9, 4.8))
    ys = sorted(scenes, reverse=True)
    for i, y in enumerate(ys):
        sc = scenes[y]["scenes"]
        d0 = np.datetime64(f"{y}-01-01")
        x = np.array([(np.datetime64(s["date"]) - d0).astype(int) + 1 for s in sc])
        fr = np.array([s["masked_fraction"] for s in sc]); used = np.array([s["used"] for s in sc])
        m = ax.scatter(x[used], np.full(used.sum(), i), c=fr[used], cmap="YlOrRd", vmin=0, vmax=0.35, s=70, edgecolor="#333", lw=.6, zorder=3)
        if (~used).any(): ax.scatter(x[~used], np.full((~used).sum(), i), marker="X", s=90, color="#b71c1c", zorder=3)
        ax.text(93, i, f"{scenes[y]['used']}/{len(sc)} cảnh", va="center", fontsize=9)
    ax.set_yticks(range(len(ys))); ax.set_yticklabels(ys); ax.set_xlim(0, 108)
    ax.set_xlabel("Ngày trong năm (1 = 1/1, 91 = 1/4)"); ax.grid(alpha=.3, zorder=0)
    cb = fig.colorbar(m, ax=ax, shrink=.8, pad=.02); cb.set_label(f"Tỉ lệ pixel bị che (ngưỡng loại: {int(C.MAX_MASKED_AOI * 100)}%)")
    ax.plot([], [], "X", color="#b71c1c", label="Cảnh bị loại"); ax.legend(loc="lower center", bbox_to_anchor=(0.5, 1.0), fontsize=8, frameon=False)
    save(fig, out, "p1-loc-canh")
    facts["scenes"] = {y: {"total": len(scenes[y]["scenes"]), "used": scenes[y]["used"]} for y in scenes}

    # B. Băng phổ (cắt quanh điểm nóng), phản xạ 0–1, kéo giãn 2–98% từng kênh
    r = 2200
    fig, axs = plt.subplots(1, 4, figsize=(11, 3.2))
    for k, (lab, ax) in enumerate(zip(["B03 · Xanh lục", "B08 · NIR", "B11 · SWIR1", "B12 · SWIR2"], axs)):
        v = a[k].values; lo, hi = np.nanpercentile(v, [2, 98])
        ax.imshow(v, extent=ext, cmap="gray", vmin=lo, vmax=hi, interpolation="nearest")
        ax.set_xlim(hs.x - r, hs.x + r); ax.set_ylim(hs.y - r * 0.7, hs.y + r * 0.7); ax.set_title(lab, fontsize=10); ax.set_axis_off()
    fig.tight_layout(); save(fig, out, "p2-bang-pho")

    # C. Cắt vùng: ranh giới xã và vùng phân tích (đệm 1 km) trên composite
    zone = commune.buffer(s2_water.ANALYSIS_BUFFER)
    fig, ax = plt.subplots(figsize=(9, 6.2)); ax.imshow(rgb, extent=ext)
    zone.boundary.plot(ax=ax, color="#00e5ff", lw=1.2); commune.boundary.plot(ax=ax, color="yellow", lw=1.2, ls="--"); ax.set_axis_off()
    ax.plot([], [], color="yellow", ls="--", label="Ranh giới xã"); ax.plot([], [], color="#00e5ff", label=f"Vùng phân tích (đệm {s2_water.ANALYSIS_BUFFER} m)")
    ax.legend(loc="lower left", fontsize=8, framealpha=.9)
    save(fig, out, "p3-cat-vung")

    # D. Histogram MNDWI trong vùng phân tích + ngưỡng Otsu (tính lại bằng đúng hàm của s2_water và so với thresholds.json)
    idx = s2_water.indices(a)["mndwi"]; mask = s2_water.analysis_mask(a)
    valid = mask & np.isfinite(idx); vals = idx[valid]
    t = float(threshold_otsu(vals)); stored = json.loads((data / "water" / "thresholds.json").read_text())[str(YEAR)]["mndwi"]
    assert abs(t - stored) < 1e-3, (t, stored)
    fig, ax = plt.subplots(figsize=(7.5, 4))
    ax.hist(vals, bins=200, color="#607d8b"); ax.axvline(t, color="#d32f2f", lw=2, label=f"Ngưỡng Otsu = {vn(stored, 3)}")
    ax.axvspan(-1, t, color="#d9c9a3", alpha=.35, label="Đất"); ax.axvspan(t, 1, color="#1e5fa8", alpha=.2, label="Nước")
    ax.set_xlim(-1, 1); ax.set_xlabel("MNDWI"); ax.set_ylabel("Số pixel"); ax.legend(fontsize=8); ax.grid(alpha=.3)
    save(fig, out, "p4-otsu")
    facts["otsu"] = {"year": YEAR, "threshold": stored, "pixels_in_zone": int(valid.sum()), "water_share": round(float((vals > t).mean()), 4)}

    # E. Trước/sau làm sạch mặt nạ: lấy cửa sổ 2,4 × 1,6 km vừa có pixel bị bỏ vừa còn nhiều nước giữ lại
    raw = rioxarray.open_rasterio(data / "water" / f"{YEAR}_mndwi.tif")[0].values
    raw_f, _ = s3_shoreline.fill_small_gaps(raw.copy())
    before = raw_f == 1; after = s3_shoreline.clean_water(before)
    diff = before != after
    h, w = 160, 240  # pixel 10 m
    best, bpos = -1, (0, 0)
    for r0 in range(0, diff.shape[0] - h, 40):
        for c0 in range(0, diff.shape[1] - w, 40):
            n = min(diff[r0:r0 + h, c0:c0 + w].sum(), after[r0:r0 + h, c0:c0 + w].sum())
            if n > best: best, bpos = n, (r0, c0)
    r0, c0 = bpos; sl = (slice(r0, r0 + h), slice(c0, c0 + w))
    wext = [b[0] + c0 * 10, b[0] + (c0 + w) * 10, b[3] - (r0 + h) * 10, b[3] - r0 * 10]
    cm = ListedColormap(["#d9c9a3", "#1e5fa8", "#d32f2f"])
    fig, axs = plt.subplots(1, 2, figsize=(11, 4))
    axs[0].imshow(before[sl].astype(int), extent=wext, cmap=cm, vmin=0, vmax=2, interpolation="nearest"); axs[0].set_title("Trước: mặt nạ Otsu", fontsize=10)
    show = after.astype(int); show[before & ~after] = 2; show[~before & after] = 1
    axs[1].imshow(show[sl], extent=wext, cmap=cm, vmin=0, vmax=2, interpolation="nearest"); axs[1].set_title("Sau: đã làm sạch (đỏ = pixel nước bị bỏ)", fontsize=10)
    for ax in axs: ax.set_axis_off()
    fig.tight_layout(); save(fig, out, "p5-truoc-sau")
    facts["clean"] = {"year": YEAR, "water_before_px": int(before.sum()), "water_after_px": int(after.sum()),
                      "removed_px": int((before & ~after).sum()), "added_px": int((~before & after).sum()),
                      "removed_ha": round(float((before & ~after).sum()) * C.RESOLUTION ** 2 / 1e4, 1),
                      "window_m": [w * 10, h * 10]}

    # F. Phóng to một đoạn bờ: pixel 10 m, 9 đường bờ, transect và giao điểm
    rz = 500; cx, cy = hs.x, hs.y
    box = (cx - rz, cy - rz * 0.6, cx + rz, cy + rz * 0.6)
    fig, ax = plt.subplots(figsize=(9, 5.6)); ax.imshow(rgb, extent=ext, interpolation="nearest")
    ax.set_xlim(box[0], box[2]); ax.set_ylim(box[1], box[3]); ax.set_axis_off()
    cmap = plt.get_cmap("viridis")
    shores = {y: gpd.read_file(data / "shoreline" / f"{y}.json").to_crs(C.CRS).geometry.iloc[0] for y in C.YEARS}
    from shapely.geometry import box as sbox
    inbox = tr[tr.intersects(sbox(*box))]
    n_in = 0
    for _, row in inbox.iterrows():
        k = row["cls"]; ax.plot(*row.geometry.xy, color=CLASS_COLOR[k], lw=1.1, alpha=.9)
        for i, y in enumerate(C.YEARS):
            x = row.geometry.intersection(shores[y]); pts = [g for g in getattr(x, "geoms", [x]) if g.geom_type == "Point"]
            if pts:
                q = min(pts, key=lambda p: p.distance(Point(row.geometry.coords[0]))); ax.plot(q.x, q.y, "o", ms=3.2, color=cmap(i / 8), mec="white", mew=.3, zorder=4)
        n_in += 1
    for i, y in enumerate(C.YEARS): gpd.GeoSeries([shores[y]], crs=C.CRS).plot(ax=ax, color=cmap(i / 8), lw=1.2, label=str(y))
    ax.legend(ncol=3, fontsize=8, loc="upper right", title="Năm", framealpha=.9)
    save(fig, out, "p6-phong-to-transect")
    facts["zoom"] = {"window_m": [2 * rz, int(1.2 * rz)], "transects": n_in}

    # G. Độ chính xác: F1 trung bình và RMSE đường bờ theo chỉ số (output/evaluation.json)
    ev = json.loads((C.OUTPUT / "evaluation.json").read_text())
    names = [i.upper() for i in C.WATER_INDICES]; sel = ev["selected_index"]
    fig, axs = plt.subplots(1, 2, figsize=(9, 3.8))
    cols = ["#1e5fa8" if i == sel else "#9e9e9e" for i in C.WATER_INDICES]
    axs[0].bar(names, [ev["mean_f1"][i] for i in C.WATER_INDICES], color=cols); axs[0].set_ylim(0.9, 0.96); axs[0].set_title("F1 trung bình (trục bắt đầu từ 0,90)", fontsize=10)
    for k, i in enumerate(C.WATER_INDICES): axs[0].text(k, ev["mean_f1"][i] + .0008, vn(ev["mean_f1"][i], 4), ha="center", fontsize=9)
    axs[1].bar(names, [ev["mean_rmse_m"][i] for i in C.WATER_INDICES], color=cols); axs[1].set_title("RMSE đường bờ trung bình (m)", fontsize=10)
    for k, i in enumerate(C.WATER_INDICES): axs[1].text(k, ev["mean_rmse_m"][i] + 1, vn(ev["mean_rmse_m"][i]), ha="center", fontsize=9)
    for ax in axs: ax.grid(axis="y", alpha=.3); ax.set_axisbelow(True)
    fig.tight_layout(); save(fig, out, "p7-do-chinh-xac")

    # H. Độ bất định đường bờ từng năm: thành phần (1σ) và tổng gộp
    sm = json.loads((C.OUTPUT / "summary.json").read_text())["u_shoreline_by_year"]
    yrs = list(sm)
    fig, ax = plt.subplots(figsize=(8, 4))
    ax.bar(yrs, [sm[y] for y in yrs], color="#90a4ae", label="U gộp (1σ)")
    for y in yrs: ax.text(y, sm[y] + .3, vn(sm[y], 1), ha="center", fontsize=8)
    ax.plot(yrs, [C.U_CLASSIFY.get(int(y), C.U_CLASSIFY_DEFAULT) for y in yrs], "o", color="#d32f2f", label="U phân loại (RMSE ground truth; năm không có GT lấy 20 m)")
    ax.axhline(C.U_GEOREF, color="#1565c0", ls="--", lw=1, label=f"U định vị = {vn(C.U_GEOREF, 0)} m"); ax.axhline(C.U_PIXEL, color="#2e7d32", ls=":", lw=1.4, label=f"U nửa pixel = {vn(C.U_PIXEL, 0)} m")
    ax.set_ylabel("m"); ax.set_ylim(0, 27); ax.legend(fontsize=7.5, loc="upper center", ncol=2, framealpha=.9); ax.grid(axis="y", alpha=.3); ax.set_axisbelow(True)
    save(fig, out, "p8-bat-dinh")

    # I. Sơ đồ xuất dữ liệu: pipeline -> PostGIS -> GeoServer (WMS/WFS) -> web
    fig, ax = plt.subplots(figsize=(10, 3.6)); ax.set_xlim(0, 100); ax.set_ylim(0, 36); ax.axis("off")
    boxes = [(1, "pipeline/output/\n*.json (EPSG:4326)\nbaseline, shorelines,\ntransects, hotspots,\nreference", "#eceff1"),
             (21, "php artisan\nwebgis:import\nUPSERT, một giao dịch\n(--dry-run để thử)", "#e3f2fd"),
             (41, "PostGIS\nshorelines, transects,\nerosion_hotspots,\nreference_landslide_\nevents", "#e8f5e9"),
             (61, "GeoServer\nworkspace songhau\nWMS + WFS, SLD\nlớp nền nen_ban_do", "#fff3e0"),
             (81, "Web Leaflet\nlớp WMS, bảng,\nbiểu đồ, tải xuống", "#f3e5f5")]
    for x, t, c in boxes:
        ax.add_patch(plt.Rectangle((x, 6), 18, 24, fc=c, ec="#455a64", lw=1.2)); ax.text(x + 9, 18, t, ha="center", va="center", fontsize=8.2)
    for x in (19, 39, 59, 79): ax.annotate("", xy=(x + 2, 18), xytext=(x, 18), arrowprops=dict(arrowstyle="->", lw=1.6, color="#455a64"))
    save(fig, out, "p9-xuat-geoserver")

    (C.OUTPUT / "web_steps.json").write_text(json.dumps(facts, indent=1, ensure_ascii=False))
    print("xong (bổ sung):", facts)


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--data", type=Path, default=C.DATA)
    ap.add_argument("--out", type=Path, default=C.ROOT.parent / "web" / "public" / "img" / "phuong-phap")
    ap.add_argument("--bo-sung", action="store_true", help="chỉ vẽ các hình chi tiết thêm")
    n = ap.parse_args()
    if not n.bo_sung: main(n.data, n.out)
    extra(n.data, n.out)
