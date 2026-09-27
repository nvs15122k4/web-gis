"""Ảnh xem nhanh composite + ranh giới xã + (tuỳ chọn) đường bờ/transect — dùng để kiểm tra trực quan và minh hoạ báo cáo."""
import json, sys
import matplotlib; matplotlib.use("Agg")
import matplotlib.pyplot as plt, numpy as np, rioxarray
import geopandas as gpd
import config as C

def main(year, out, extra=()):
    a = rioxarray.open_rasterio(C.DATA / "composite" / f"{year}.tif")
    rgb = np.clip(np.dstack([a[2].values, a[1].values, a[0].values]) / 0.35, 0, 1)
    b = a.rio.bounds()
    fig, ax = plt.subplots(figsize=(11, 8))
    ax.imshow(rgb, extent=[b[0], b[2], b[1], b[3]])
    gpd.read_file(C.COMMUNE_FILE).to_crs(C.CRS).boundary.plot(ax=ax, color="yellow", lw=1, ls="--")
    for path, color in extra:
        gpd.read_file(path).to_crs(C.CRS).plot(ax=ax, color=color, lw=0.8, markersize=30)
    ax.set_title(f"Composite mùa khô {year} (SWIR1, NIR, Green)")
    fig.tight_layout(); fig.savefig(out, dpi=90)

if __name__ == "__main__":
    main(int(sys.argv[1]), sys.argv[2], [(p, c) for p, c in (x.split(":") for x in sys.argv[3:])])
