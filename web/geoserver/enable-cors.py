#!/usr/bin/env python3
"""Bật CORS (Jetty CrossOriginFilter) trong web.xml của GeoServer để Leaflet gọi WMS trực tiếp từ trình duyệt.
Dùng: python3 geoserver/enable-cors.py $GEOSERVER_HOME   (idempotent; khởi động lại GeoServer sau khi chạy)"""
import re
import sys

path = f"{sys.argv[1]}/webapps/geoserver/WEB-INF/web.xml"
xml = open(path, encoding="utf-8").read()

# Khối filter Jetty: comment bao quanh chứa "org.eclipse.jetty.servlets.CrossOriginFilter"
filter_re = re.compile(r"<!--\s*(<filter>\s*<filter-name>cross-origin</filter-name>\s*<filter-class>org\.eclipse\.jetty\.servlets\.CrossOriginFilter.*?</filter>)\s*-->", re.S)
mapping_re = re.compile(r"<!--\s*(<filter-mapping>\s*<filter-name>cross-origin</filter-name>.*?</filter-mapping>)\s*-->", re.S)

if filter_re.search(xml) is None and "org.eclipse.jetty.servlets.CrossOriginFilter" in xml:
    print("CORS đã được bật.")
    sys.exit(0)

xml, n1 = filter_re.subn(r"\1", xml, count=1)
xml, n2 = mapping_re.subn(r"\1", xml, count=1)
if not (n1 and n2):
    sys.exit("Không tìm thấy khối CORS để bật trong web.xml")
open(path, "w", encoding="utf-8").write(xml)
print("Đã bật CORS trong web.xml.")
