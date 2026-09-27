"""Tham số pipeline — chốt theo đề cương (Mục 2.2, 2.3, 2.5). Sửa ở đây, không rải hằng số trong các bước."""
from pathlib import Path

ROOT = Path(__file__).parent
DATA = ROOT / "data"
OUTPUT = ROOT / "output"          # thư mục GeoJSON cho `php artisan webgis:import`

# Vùng tải ảnh (EPSG:4326): đoạn sông Hậu qua xã Bình Mỹ + vùng đệm. Phân tích chi tiết cắt theo ranh giới xã.
BBOX = (105.17, 10.45, 105.36, 10.575)
COMMUNE_FILE = DATA / "ref" / "binhmy_osm.geojson"   # ranh giới xã (OSM relation 13646056, sau sáp nhập 2025)
CRS = "EPSG:32648"                 # UTM 48N — đo khoảng cách theo mét
RESOLUTION = 10                    # m, resample mọi kênh về 10 m (Mục 2.3)

YEARS = list(range(2018, 2027))
SEASON = ("01-01", "03-31")        # mùa khô tháng 1–3 (Mục 2.2)

# Lọc mây bằng SCL (Mục 2.3)
STAC_URL = "https://planetarycomputer.microsoft.com/api/stac/v1"
COLLECTION = "sentinel-2-l2a"
PREFILTER_CLOUD = 80               # % mây cả cảnh (metadata) — chỉ để bớt tải
MAX_MASKED_AOI = 0.30              # loại ảnh có >30% pixel bị che trong vùng tải
SCL_MASK = [0, 1, 3, 8, 9, 10, 11] # no-data, bão hoà, bóng mây, mây TB/cao, mây ti, tuyết
BANDS = ["B03", "B08", "B11", "B12", "SCL"]

WATER_INDICES = ["ndwi", "mndwi", "awei"]
DEFAULT_INDEX = "mndwi"           # dùng tới khi có kết quả đánh giá Mục 2.4 thì đổi theo chỉ số tốt nhất

# Transect (Mục 2.3)
TRANSECT_SPACING = 50              # m
BASELINE_OFFSET = 250              # m, đặt baseline trên bờ (phía đất) cách đường bờ trung vị
TRANSECT_LENGTH = 600              # m, từ baseline hướng ra sông
SMOOTH_TOLERANCE = 5               # m (nửa pixel), đơn giản hoá đường bờ sau vector hoá — lớn hơn sẽ nuốt biến động nhỏ

# Loại quan sát nhiễu (despike, như CoastSat — Vos et al., 2019): khoảng cách lệch > DESPIKE_M so với trung vị
# chuỗi của transect là do transect xuyên qua miệng kênh/cắt nhầm bờ khác, không phải biến động thật.
DESPIKE_M = 50

# Độ bất định (Mục 2.5b): U_shoreline = sqrt(U_pixel² + U_georef² + U_classify²)
U_PIXEL = 5.0                      # nửa pixel 10 m
U_GEOREF = 11.0                    # ESA: sai số định vị tuyệt đối L2A (2σ) — xác nhận lại theo Data Quality Report
U_CLASSIFY = {}                    # {năm: RMSE m} — điền sau khi đánh giá ground truth (Mục 2.4)
U_CLASSIFY_DEFAULT = 10.0          # tạm dùng khi chưa có RMSE, ghi rõ trong báo cáo

# Phân loại (Mục 2.5a)
Z95 = 1.96
MIN_CONSECUTIVE_DECLINE = 3        # số mốc năm liên tiếp khoảng cách giảm dần
R2_CAUTION = 0.3
