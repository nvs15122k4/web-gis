#!/usr/bin/env bash
# Tạo workspace, PostGIS store, style và publish layer qua REST API (idempotent).
# Dùng: geoserver/provision.sh <workspace> <database>      vd: provision.sh songhau webgis_spatial
set -euo pipefail

WS="${1:?workspace}"; DB="${2:?database}"
GS="${GEOSERVER_URL:-http://127.0.0.1:8080/geoserver}"
AUTH="${GEOSERVER_AUTH:-admin:geoserver}"
# GEOSERVER_PGHOST: địa chỉ PostGIS nhìn từ GeoServer (chạy Docker thì là "postgis").
PGHOST="${GEOSERVER_PGHOST:-${DB_PGSQL_HOST:-127.0.0.1}}"; PGPORT="${DB_PGSQL_PORT:-5432}"
PGUSER="${DB_PGSQL_USERNAME:-postgres}"; PGPASS="${DB_PGSQL_PASSWORD:-}"
HERE="$(cd "$(dirname "$0")" && pwd)"
STORE=postgis

rest() { curl -s -u "$AUTH" -o /dev/null -w '%{http_code}' "$@"; }
json() { curl -s -u "$AUTH" -H 'Content-Type: application/json' "$@"; }

echo "→ workspace $WS"
[ "$(rest "$GS/rest/workspaces/$WS")" = 200 ] || json -X POST -d "{\"workspace\":{\"name\":\"$WS\"}}" "$GS/rest/workspaces" >/dev/null

# store <tên> <schema>: tạo hoặc cập nhật PostGIS store trỏ tới một schema.
store() {
  local body
  body=$(cat <<JSON
{"dataStore":{"name":"$1","type":"PostGIS","enabled":true,"connectionParameters":{"entry":[
 {"@key":"host","$":"$PGHOST"},{"@key":"port","$":"$PGPORT"},{"@key":"database","$":"$DB"},
 {"@key":"schema","$":"$2"},{"@key":"user","$":"$PGUSER"},{"@key":"passwd","$":"$PGPASS"},
 {"@key":"dbtype","$":"postgis"},{"@key":"Expose primary keys","$":"true"}]}}}
JSON
)
  if [ "$(rest "$GS/rest/workspaces/$WS/datastores/$1")" = 200 ]; then
    json -X PUT -d "$body" "$GS/rest/workspaces/$WS/datastores/$1" >/dev/null
  else
    json -X POST -d "$body" "$GS/rest/workspaces/$WS/datastores" >/dev/null
  fi
}

# style <tên>: tạo (nếu chưa có) rồi nạp lại nội dung từ styles/<tên>.sld.
style() {
  if [ "$(rest "$GS/rest/workspaces/$WS/styles/$1")" != 200 ]; then
    json -X POST -d "{\"style\":{\"name\":\"$1\",\"filename\":\"$1.sld\"}}" "$GS/rest/workspaces/$WS/styles" >/dev/null
  fi
  curl -s -u "$AUTH" -X PUT -H 'Content-Type: application/vnd.ogc.sld+xml' \
    --data-binary "@$HERE/styles/$1.sld" "$GS/rest/workspaces/$WS/styles/$1" >/dev/null
}

# publish <store> <layer> <bảng nguồn> <style mặc định>
publish() {
  if [ "$(rest "$GS/rest/workspaces/$WS/datastores/$1/featuretypes/$2")" != 200 ]; then
    json -X POST -d "{\"featureType\":{\"name\":\"$2\",\"nativeName\":\"$3\",\"srs\":\"EPSG:4326\",\"projectionPolicy\":\"REPROJECT_TO_DECLARED\",\"enabled\":true}}" \
      "$GS/rest/workspaces/$WS/datastores/$1/featuretypes" >/dev/null
  fi
  json -X PUT -d "{\"layer\":{\"defaultStyle\":{\"name\":\"$WS:$4\"}}}" "$GS/rest/workspaces/$WS/layers/$2" >/dev/null
}

echo "→ PostGIS store ($DB)"
store "$STORE" public

echo "→ styles"
for sld in change_class shoreline shoreline_by_year hotspot reference; do style "$sld"; done

# layer:bảng nguồn:style mặc định
echo "→ publish layers"
for spec in shorelines:shorelines:shoreline transects:transects_styled:change_class \
            erosion_hotspots:erosion_hotspots:hotspot reference_landslide_events:reference_landslide_events:reference; do
  IFS=: read -r name native sld <<<"$spec"
  publish "$STORE" "$name" "$native" "$sld"
  echo "   $WS:$name ← $native (style $sld)"
done

# Dọn dấu vết bản provision cũ: bộ lọc CQL theo cột `source` (cột đã gỡ, làm lớp shorelines báo lỗi khi vẽ) và lớp shorelines_alt.
json -X PUT -d '{"featureType":{"cqlFilter":""}}' "$GS/rest/workspaces/$WS/datastores/$STORE/featuretypes/shorelines" >/dev/null || true
rest -X DELETE "$GS/rest/workspaces/$WS/layers/shorelines_alt" >/dev/null || true

# WFS (đề cương hứa WMS + WFS): các lớp trên cũng phục vụ qua WFS (GeoJSON, lọc CQL/bbox). Bật dịch vụ WFS riêng cho workspace
# và nạp lại cấu hình để cột mới của view/bảng (vd. unc_lrr) xuất hiện trong schema lớp.
echo "→ dịch vụ WFS"
json -X PUT -d "{\"wfs\":{\"enabled\":true,\"name\":\"WFS\",\"title\":\"WFS Sông Hậu\",\"maxFeatures\":5000,\"serviceLevel\":\"BASIC\"}}" \
  "$GS/rest/services/wfs/workspaces/$WS/settings" >/dev/null || true
[ "$(rest -X POST "$GS/rest/reload")" = 200 ] || echo "   (không nạp lại được cấu hình GeoServer — khởi động lại thủ công nếu thiếu cột mới)"

# Nền bản đồ tự phục vụ (không lấy tile bên thứ ba, không có "đường lưỡi bò"): dữ liệu do
# geoserver/basemap/build_basemap.py nạp vào schema basemap. Thứ tự dưới đây là thứ tự vẽ (dưới → trên).
BASEMAP="ocean countries ne_water ne_rivers osm_residential osm_water osm_waterways provinces borders osm_roads
         sea_labels archipelagos country_labels province_labels cities osm_places"
store basemap basemap
if curl -s -u "$AUTH" "$GS/rest/workspaces/$WS/datastores/basemap/featuretypes.json?list=all" | grep -q '"ocean"'; then
  echo "→ nền bản đồ (layer group $WS:nen_ban_do)"
  published=(); styles=()
  for t in $BASEMAP; do
    style "nen_$t"
    publish basemap "nen_$t" "$t" "nen_$t"
    published+=("{\"@type\":\"layer\",\"name\":\"$WS:nen_$t\"}"); styles+=("{\"name\":\"$WS:nen_$t\"}")
  done
  group=$(IFS=,; echo "{\"layerGroup\":{\"name\":\"nen_ban_do\",\"mode\":\"SINGLE\",\"title\":\"Nền bản đồ (Natural Earth quan điểm VN + OSM)\",
    \"publishables\":{\"published\":[${published[*]}]},\"styles\":{\"style\":[${styles[*]}]}}}")
  if [ "$(rest "$GS/rest/workspaces/$WS/layergroups/nen_ban_do")" = 200 ]; then
    json -X PUT -d "$group" "$GS/rest/workspaces/$WS/layergroups/nen_ban_do" >/dev/null
  else
    json -X POST -d "$group" "$GS/rest/workspaces/$WS/layergroups" >/dev/null
  fi
  # Xoá tile cũ trong GeoWebCache để style mới có hiệu lực ngay.
  curl -s -u "$AUTH" -X POST -H 'Content-Type: text/xml' \
    -d "<truncateLayer><layerName>$WS:nen_ban_do</layerName></truncateLayer>" "$GS/gwc/rest/masstruncate" >/dev/null
  # Vẽ sẵn tile cho vùng web có thể hiển thị (khung nhìn neo quanh Bình Mỹ, zoom 5–16) để lần xem đầu không bị trống.
  seed() {
    curl -s -u "$AUTH" -X POST -H 'Content-Type: application/json' -d "{\"seedRequest\":{\"name\":\"$WS:nen_ban_do\",
      \"gridSetId\":\"EPSG:900913\",\"zoomStart\":$1,\"zoomStop\":$2,\"format\":\"image/png\",\"type\":\"seed\",
      \"threadCount\":2,\"bounds\":{\"coords\":{\"double\":[$3]}}}}" "$GS/gwc/rest/seed/$WS:nen_ban_do.json" >/dev/null
  }
  seed 5 9 "10200000,-500000,13500000,3000000"        # Đông Nam Á, Biển Đông (EPSG:3857)
  seed 10 16 "11695000,1158000,11745000,1197000"     # xã Bình Mỹ và đoạn sông Hậu
  echo "   $WS:nen_ban_do ← $(echo $BASEMAP | wc -w) lớp (đang seed cache GeoWebCache nền)"
else
  echo "→ bỏ qua nền bản đồ: chưa có schema basemap (chạy geoserver/basemap/build_basemap.py trước)"
fi
echo "Xong. Kiểm tra: $GS/$WS/wms?service=WMS&request=GetCapabilities  và  $GS/$WS/wfs?service=WFS&request=GetCapabilities"
