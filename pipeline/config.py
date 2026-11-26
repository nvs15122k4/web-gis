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
SELECT_TIE_F1 = 0.005             # chênh F1 TB ≤ ngưỡng này thì coi là hoà và xét RMSE đường bờ (s7_evaluate.select_index, Mục 3.4)
# Engine giải đoán đường bờ: "qgis" = đường bờ do QGIS tự giải đoán từ ảnh (qgis_interpret.py: MNDWI → Otsu → sieve → polygonize),
# "python" = đường bờ contour dưới pixel của s3_shoreline.vectorize. Raster nước của s2 chỉ còn dùng để xác định phía đất khi dựng baseline (s4).
SHORELINE_ENGINE = "qgis"
DEFAULT_INDEX = "mndwi"           # F1 TB mndwi 0,9456 ≈ awei 0,9457 (hoà); chọn mndwi vì RMSE đường bờ thấp hơn nhiều (Mục 2.4)

# Transect (Mục 2.3)
TRANSECT_SPACING = 50              # m
BASELINE_OFFSET = 250              # m, đặt baseline trên bờ (phía đất) cách đường bờ trung vị
TRANSECT_LENGTH = 600              # m, từ baseline hướng ra sông
SMOOTH_TOLERANCE = 5               # m (nửa pixel), đơn giản hoá đường bờ sau vector hoá — lớn hơn sẽ nuốt biến động nhỏ

# Loại quan sát nhiễu (despike): khoảng cách lệch > DESPIKE_M so với trung vị chuỗi của transect là do transect
# xuyên qua miệng kênh/cắt nhầm bờ khác, không phải biến động thật. Ý tưởng lọc đột biến có trong mã CoastSat
# (tham số max_cross_change), không có trong bài Vos et al. (2019, Environ. Model. Softw.); ngưỡng 50 m là giả định của đồ án.
DESPIKE_M = 50

# ---------------------------------------------------------------------------------------------------------------
# Độ bất định và phân loại. Tiêu chí được CHỐT TRƯỚC khi chạy lại, không điều chỉnh theo kết quả (docs/checklist-yeu-cau.md).
# Nguồn: DSAS v5.1 (Himmelstoss et al., 2021): EPRunc = sqrt(uncA² + uncB²)/Δt, LSE chia (n − 2), KTC của LRR = SE × thống kê
# hai phía. Sentinel-2: ESA (định vị < 12 m ở 95,5%), Clerc et al. (CE95 < 12,5 m). Nửa pixel: Vos et al. (2019), Luijendijk et al. (2018).
# Là SUY LUẬN/GIẢ ĐỊNH của đồ án, không gán cho nguồn: công thức gộp U_shoreline từng đường bờ, quy 12 m (95,5%) về 6 m (1σ),
# gọi đúng tên "Student t, df = n − 2" cho KTC của LRR, ngưỡng khử đột biến 50 m.
# ---------------------------------------------------------------------------------------------------------------
# Độ bất định đường bờ (Mục 2.5b), MỌI thành phần quy về cùng chuẩn 1σ:
#   U_shoreline(1σ) = sqrt(U_pixel² + U_georef² + U_classify²)
# Độ bất định EPR và LRR báo cáo ở độ tin cậy 95% (CONFIDENCE).
CONFIDENCE = 0.95
Z95 = 1.959964                     # hệ số phủ chuẩn 95% cho độ bất định lan truyền từ nguồn ngoài (EPR); LRR dùng t(df = n − 2)
U_PIXEL = 5.0                      # m, nửa pixel 10 m (nửa pixel, Vos et al. 2019; xem là 1σ — giả định của đồ án)
U_GEOREF_95 = 12.0                 # m, ESA: định vị tuyệt đối Sentinel-2 L1C < 12 m ở độ tin cậy 95,5% (2σ) — ESA, đặc tả sản phẩm; quy về 1σ là suy luận của đồ án
GEOREF_SIGMA_K = 2.0               # 95,5% ứng với 2σ
U_GEOREF = U_GEOREF_95 / GEOREF_SIGMA_K   # m, quy về 1σ = 6 m để cộng bình phương cùng chuẩn với U_pixel/U_classify
# RMSE đường bờ CHÍNH (bản QGIS) vs ground_truth, trên các quan sát còn lại sau despike — đúng tập quan sát dùng cho NSM/EPR/LRR
# (output/evaluation.json: u_classify_suggestion, cơ sở ghi ở u_classify_basis; Mục 2.4). Xem là 1σ. Số đo từ dữ liệu, không phải tiêu chí tự chọn.
# Năm 2018 có thêm 3 transect (T0025–T0027, ô W1) bị despike loại vì QGIS giữ một vùng nước phía đất làm bờ (lệch 276–307 m); RMSE tất cả
# 59 transect là 66,95 m (evaluation.json: main_shoreline_error.all). Trước khi đổi sang đường bờ QGIS, giá trị đo trên bản Python là 15,1 / 18,39 / 19,99.
U_CLASSIFY = {2018: 17.15, 2022: 17.82, 2026: 21.69}
U_CLASSIFY_DEFAULT = 21.7          # các năm không có ground truth: lấy giá trị lớn nhất đã đo, làm tròn lên (thận trọng)

# Phân loại biến động (Mục 2.5a). Ngưỡng tuyệt đối, đặt trước, không phụ thuộc phân phối kết quả.
# −2 m/năm là QUY ƯỚC CỦA ĐỒ ÁN, tham chiếu khoảng phân lớp xói của Luijendijk et al. (2018) và Nguyen & Khoi (2020);
# không có tài liệu nào dùng đúng ngưỡng này. Số mốc liên tiếp ≥ 3 cũng là quy ước của đồ án.
SEVERE_LRR = -2.0                  # m/năm: lùi bờ mạnh khi LRR <= SEVERE_LRR
MIN_CONSECUTIVE_DECLINE = 3        # số mốc năm liên tiếp khoảng cách giảm dần (3 mốc = 2 giai đoạn liền nhau)
R2_CAUTION = 0.3                   # chỉ là CỜ chất lượng (low_r2_caution), không dùng để loại hay đổi nhóm

# Đối chiếu sạt lở tham chiếu (s9_validate, Mục 2.4): khớp khi hotspot cách vùng cảnh báo ≤ MATCH_RADIUS; thống kê transect trong vùng đệm ZONE_BUFFER.
MATCH_RADIUS = 500                 # m — quy ước của đồ án (cùng bậc độ không chắc chắn vị trí của vùng cảnh báo)
ZONE_BUFFER = 100                  # m — transect cách vùng cảnh báo ≤ ngưỡng này coi là thuộc vùng
