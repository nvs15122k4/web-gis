"""Sinh ảnh minh hoạ cho trang Phương pháp (/gioi-thieu) từ dữ liệu pipeline đã có.

Chỉ vẽ lại dữ liệu trung gian (composite, MNDWI, mặt nạ nước, đường bờ, transect), không tính lại chỉ số.
    .venv/bin/python web_steps.py [--data <thư mục data>] [--out <thư mục ảnh>]
"""
import argparse, json
from pathlib import Path
import matplotlib; matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np, rioxarray, geopandas as gpd
from matplotlib.colors import ListedColormap
from PIL import Image
import config as C

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
    hs = tr[tr["cls"] == "severe_erosion"].geometry.union_all().centroid
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
    row = tr[tr["cls"] == "severe_erosion"].iloc[0]
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


if __name__ == "__main__":
    ap = argparse.ArgumentParser()
    ap.add_argument("--data", type=Path, default=C.DATA)
    ap.add_argument("--out", type=Path, default=C.ROOT.parent / "web" / "public" / "img" / "phuong-phap")
    n = ap.parse_args(); main(n.data, n.out)
