# Năm 2021 — giải đoán đường bờ bằng giao diện QGIS 3.40.15 (bản nháp, đang làm)

Ảnh chụp: `docs/minh_chung/qgis/2021/NN_*.png`. Dữ liệu: thư mục này.

## Mẹo thao tác (màn hình ảo Xvfb, không WM)
- Khởi động QGIS KHÔNG qua bộ gõ fcitx: `env -u QT_IM_MODULE -u XMODIFIERS GTK_IM_MODULE=gtk-im-context-simple gui.sh D start`
  (nếu không, xdotool type qua Unikey biến "data" → "dât", đường dẫn sai). Script: scratchpad/start112.sh.
- Hộp thoại file là GTK: gõ thẳng đường dẫn đầy đủ vào ô Name rồi Enter.
- Phím tắt (ctrl+s, ctrl+l, ctrl+shift+p) chỉ ăn sau khi click vào canvas để lấy focus.
- Dialog Processing nhỏ: phóng to bằng `xdotool search --name "<tiêu đề>"` rồi `windowmove/windowsize`.
- Khi đổi tên lớp: click tên lớp, F2, ctrl+a, gõ tên, Enter.
- Tắt ô "Render" (góc phải dưới) khi nạp nhiều lớp COG từ mạng để QGIS không tải ảnh liên tục.
- Batch Clip: Autofill > Select from Open Layers > Select All; cột Mask/CRS/độ phân giải: đặt hàng 2 rồi Autofill > Fill Down;
  cột output: nút "…" hàng 2, gõ `.../clip/c.tif`, chọn "Fill with parameter values" theo Input layer.
