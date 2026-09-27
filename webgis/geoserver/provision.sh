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

echo "→ PostGIS store ($DB)"
STORE_JSON=$(cat <<JSON
{"dataStore":{"name":"$STORE","type":"PostGIS","enabled":true,"connectionParameters":{"entry":[
 {"@key":"host","$":"$PGHOST"},{"@key":"port","$":"$PGPORT"},{"@key":"database","$":"$DB"},
 {"@key":"schema","$":"public"},{"@key":"user","$":"$PGUSER"},{"@key":"passwd","$":"$PGPASS"},
 {"@key":"dbtype","$":"postgis"},{"@key":"Expose primary keys","$":"true"}]}}}
JSON
)
if [ "$(rest "$GS/rest/workspaces/$WS/datastores/$STORE")" = 200 ]; then
  json -X PUT -d "$STORE_JSON" "$GS/rest/workspaces/$WS/datastores/$STORE" >/dev/null
else
  json -X POST -d "$STORE_JSON" "$GS/rest/workspaces/$WS/datastores" >/dev/null
fi

echo "→ styles"
for sld in change_class shoreline hotspot reference; do
  if [ "$(rest "$GS/rest/workspaces/$WS/styles/$sld")" != 200 ]; then
    json -X POST -d "{\"style\":{\"name\":\"$sld\",\"filename\":\"$sld.sld\"}}" "$GS/rest/workspaces/$WS/styles" >/dev/null
  fi
  curl -s -u "$AUTH" -X PUT -H 'Content-Type: application/vnd.ogc.sld+xml' \
    --data-binary "@$HERE/styles/$sld.sld" "$GS/rest/workspaces/$WS/styles/$sld" >/dev/null
done

# layer:bảng nguồn:style mặc định
echo "→ publish layers"
for spec in shorelines:shorelines:shoreline transects:transects_styled:change_class \
            erosion_hotspots:erosion_hotspots:hotspot reference_landslide_events:reference_landslide_events:reference; do
  IFS=: read -r name native style <<<"$spec"
  if [ "$(rest "$GS/rest/workspaces/$WS/datastores/$STORE/featuretypes/$name")" != 200 ]; then
    json -X POST -d "{\"featureType\":{\"name\":\"$name\",\"nativeName\":\"$native\",\"srs\":\"EPSG:4326\",\"projectionPolicy\":\"REPROJECT_TO_DECLARED\",\"enabled\":true}}" \
      "$GS/rest/workspaces/$WS/datastores/$STORE/featuretypes" >/dev/null
  fi
  json -X PUT -d "{\"layer\":{\"defaultStyle\":{\"name\":\"$WS:$style\"}}}" "$GS/rest/workspaces/$WS/layers/$name" >/dev/null
  echo "   $WS:$name ← $native (style $style)"
done
echo "Xong. Kiểm tra: $GS/$WS/wms?service=WMS&request=GetCapabilities"
