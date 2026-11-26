"""Vùng nghiên cứu sau khi bo tròn phải là một đa giác hợp lệ, không có mũi nhọn tự cắt."""
from shapely.geometry import LineString, Polygon

from s6_reference import smooth_area


def test_bo_vung_tu_cat_thanh_da_giac_hop_le():
    bowtie = Polygon([(0, 0), (1000, 0), (0, 600), (1000, 600)])
    assert not bowtie.is_valid
    g = smooth_area(bowtie.buffer(0))
    assert g.is_valid and g.geom_type == "Polygon"


def test_bo_vung_loai_mui_nhon_va_giu_dai():
    strip = LineString([(0, 0), (4000, 0)]).buffer(400, cap_style=2)
    spike = LineString([(2000, 0), (2000, 1900)]).buffer(5)
    g = smooth_area(strip.union(spike))
    assert g.is_valid
    assert g.bounds[3] < 500  # mũi nhọn dài 1500 m bị loại, dải rộng 800 m vẫn còn
    assert g.area > 0.9 * strip.area
