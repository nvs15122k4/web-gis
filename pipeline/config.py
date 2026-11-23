"""Tham số pipeline — chốt theo đề cương (Mục 2.2, 2.3, 2.5). Sửa ở đây, không rải hằng số trong các bước."""
from pathlib import Path

ROOT = Path(__file__).parent
DATA = ROOT / "data"
OUTPUT = ROOT / "output"          # thư mục GeoJSON cho `php artisan webgis:import`

# Vùng tải ảnh (EPSG:4326): đoạn sông Hậu qua xã Bình Mỹ + vùng đệm. Phân tích chi tiết cắt theo ranh giới xã.
BBOX = (105.17, 10.45, 105.36, 10.575)
COMMUNE_FILE = DATA / "ref" / "binhmy_osm.json"   # ranh giới xã (OSM relation 13646056, sau sáp nhập 2025)
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
DEFAULT_INDEX = "mndwi"           # F1 TB mndwi 0,9456 ≈ awei 0,9457 (hoà); chọn mndwi vì RMSE đường bờ thấp hơn nhiều (Mục 2.4)

# Transect (Mục 2.3)
TRANSECT_SPACING = 50              # m
BASELINE_OFFSET = 250              # m, đặt baseline trên bờ (phía đất) cách đường bờ trung vị
TRANSECT_LENGTH = 600              # m, từ baseline hướng ra sông
SMOOTH_TOLERANCE = 5               # m (nửa pixel), đơn giản hoá đường bờ sau vector hoá — lớn hơn sẽ nuốt biến động nhỏ

# Loại quan sát nhiễu (despike, như CoastSat — Vos et al., 2019): khoảng cách lệch > DESPIKE_M so với trung vị
# chuỗi của transect là do transect xuyên qua miệng kênh/cắt nhầm bờ khác, không phải biến động thật.
DESPIKE_M = 50

# ---------------------------------------------------------------------------------------------------------------
# Độ bất định và phân loại. Tiêu chí được CHỐT TRƯỚC khi chạy lại, không điều chỉnh theo kết quả (docs/checklist-yeu-cau.md).
# Nguồn tham khảo cho từng tham số: danh sách nguồn chờ xác nhận ở docs/nguon-cho-xac-nhan.md (chưa dẫn vào văn bản).
# ---------------------------------------------------------------------------------------------------------------
# Độ bất định đường bờ (Mục 2.5b), MỌI thành phần quy về cùng chuẩn 1σ:
#   U_shoreline(1σ) = sqrt(U_pixel² + U_georef² + U_classify²)
# Độ bất định EPR và LRR báo cáo ở độ tin cậy 95% (CONFIDENCE).
CONFIDENCE = 0.95
Z95 = 1.959964                     # hệ số phủ chuẩn 95% cho độ bất định lan truyền từ nguồn ngoài (EPR); LRR dùng t(df = n − 2)
U_PIXEL = 5.0                      # m, nửa pixel 10 m (xem là 1σ; chưa có nguồn riêng — ghi nhận là giả định)
U_GEOREF_95 = 12.0                 # m, ESA: định vị tuyệt đối Sentinel-2 L1C < 12 m ở độ tin cậy 95,5% (2σ) — đặc tả sản phẩm
GEOREF_SIGMA_K = 2.0               # 95,5% ứng với 2σ
U_GEOREF = U_GEOREF_95 / GEOREF_SIGMA_K   # m, quy về 1σ = 6 m để cộng bình phương cùng chuẩn với U_pixel/U_classify
U_CLASSIFY = {2018: 15.1, 2022: 18.39, 2026: 19.99}  # RMSE đường bờ vs ground_truth (output/evaluation.json, Mục 2.4), xem là 1σ
U_CLASSIFY_DEFAULT = 20.0          # các năm không có ground truth: lấy giá trị lớn nhất đã đo (thận trọng)

# Phân loại biến động (Mục 2.5a). Ngưỡng tuyệt đối, đặt trước, không phụ thuộc phân phối kết quả.
SEVERE_LRR = -2.0                  # m/năm: lùi bờ mạnh khi LRR <= SEVERE_LRR
MIN_CONSECUTIVE_DECLINE = 3        # số mốc năm liên tiếp khoảng cách giảm dần (3 mốc = 2 giai đoạn liền nhau)
R2_CAUTION = 0.3                   # chỉ là CỜ chất lượng (low_r2_caution), không dùng để loại hay đổi nhóm
