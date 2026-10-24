"""Dựng dữ liệu bản đồ nền tự phục vụ (schema `basemap` trong PostGIS) cho GeoServer — không dùng tile của bên thứ ba.

Nguồn (đều là dữ liệu thật, tải nguyên gốc):
  - Natural Earth 1:10m (public domain). Lớp quốc gia dùng bản theo quan điểm Việt Nam (`admin_0_countries_vnm`):
    Hoàng Sa, Trường Sa thuộc Việt Nam, không có "đường lưỡi bò".
  - Danh mục hành chính sau sáp nhập 01/07/2025 (34 tỉnh) từ https://provinces.open-api.vn — suy bảng đối chiếu
    63 tỉnh cũ → 34 tỉnh mới bằng mã xã (mã xã mới giữ mã một xã cũ), lưu ở provinces_2025.csv.
  - OpenStreetMap (© OpenStreetMap contributors, ODbL) qua Overpass API, chỉ khung quanh khu vực nghiên cứu:
    đường, sông/kênh, mặt nước, khu dân cư, địa danh — để nền có chi tiết khi phóng to.

Chạy (cần ogr2ogr, psql; biến DB_PGSQL_* như provision.sh):
    set -a && . ./.env && set +a && python3 geoserver/basemap/build_basemap.py [--refresh]
rồi geoserver/provision.sh songhau webgis_spatial để publish layer group `nen_ban_do`.
"""
import csv
import json
import os
import re
import subprocess
import sys
import unicodedata
import urllib.parse
import urllib.request
from collections import Counter, defaultdict
from pathlib import Path

HERE = Path(__file__).parent
CACHE = HERE / ".cache"
MAPPING = HERE / "provinces_2025.csv"
SCHEMA = "basemap"

NE_URL = "https://naciscdn.org/naturalearth/10m/{}.zip"
NE = {  # bảng tạm ← file Natural Earth
    "src_countries": "cultural/ne_10m_admin_0_countries_vnm",
    "src_borders": "cultural/ne_10m_admin_0_boundary_lines_land",
    "src_admin1": "cultural/ne_10m_admin_1_states_provinces",
    "src_ocean": "physical/ne_10m_ocean",
    "src_marine": "physical/ne_10m_geography_marine_polys",
    "src_places": "cultural/ne_10m_populated_places",
    "src_rivers": "physical/ne_10m_rivers_lake_centerlines",
    "src_lakes": "physical/ne_10m_lakes",
}
REGION = (90, -12, 135, 35)  # Đông Nam Á + Biển Đông: giới hạn lớp điểm/sông/biển cho nhẹ
PROVINCES_API = "https://provinces.open-api.vn/api"
# Natural Earth ghi sai tên 3 tỉnh (VN-39 "Đông Nam Bộ", VN-53 "Vùng Đông Bắc", VN-66 "Đồng Bằng Sông Hồng"); sửa theo ISO 3166-2:VN.
NE_ISO_FIX = {"VN-39": "Đồng Nai", "VN-53": "Bắc Kạn", "VN-66": "Hưng Yên"}

OSM_BBOX = (10.30, 105.05, 10.75, 105.55)  # (S, W, N, E): Châu Phú – Long Xuyên – Chợ Mới, bao xã Bình Mỹ
OVERPASS = "https://overpass-api.de/api/interpreter"
OSM_QUERY = """[out:xml][timeout:300];
(
  way["highway"~"^(motorway|trunk|primary|secondary|tertiary|unclassified|residential)$"]({b});
  way["waterway"~"^(river|canal)$"]({b});
  way["natural"="water"]({b}); relation["natural"="water"]({b});
  way["waterway"="riverbank"]({b}); relation["waterway"="riverbank"]({b});
  way["landuse"="residential"]({b});
  node["place"~"^(city|town|village|suburb|quarter|hamlet)$"]({b});
);
(._;>;);
out body qt;"""

# Đảo của Việt Nam lấy từ lớp quốc gia (quan điểm VN), gán đơn vị hành chính theo pháp luật Việt Nam.
ARCHIPELAGOS = [
    ("Quần đảo Hoàng Sa", "Thành phố Đà Nẵng", (110.5, 15.5, 113.0, 17.3)),
    ("Quần đảo Trường Sa", "Tỉnh Khánh Hòa", (111.5, 6.0, 117.5, 12.0)),
]


def pg_env():
    return {
        **os.environ,
        "PGHOST": os.environ.get("DB_PGSQL_HOST", "127.0.0.1"),
        "PGPORT": os.environ.get("DB_PGSQL_PORT", "5432"),
        "PGDATABASE": os.environ.get("DB_PGSQL_DATABASE", "webgis_spatial"),
        "PGUSER": os.environ.get("DB_PGSQL_USERNAME", "postgres"),
        "PGPASSWORD": os.environ.get("DB_PGSQL_PASSWORD", ""),
    }


def psql(sql):
    subprocess.run(["psql", "-v", "ON_ERROR_STOP=1", "-q", "-c", sql], env=pg_env(), check=True)


def ogr_load(src, table, *opts):
    e = pg_env()
    dst = f"PG:host={e['PGHOST']} port={e['PGPORT']} dbname={e['PGDATABASE']} user={e['PGUSER']} password={e['PGPASSWORD']}"
    subprocess.run(["ogr2ogr", "-f", "PostgreSQL", dst, src, "-overwrite", "-nln", f"{SCHEMA}.{table}",
                    "-t_srs", "EPSG:4326", "-lco", "GEOMETRY_NAME=geom", "-lco", "SPATIAL_INDEX=NONE",
                    "-nlt", "PROMOTE_TO_MULTI", *opts], env=e, check=True)


def download(url, dest, data=None):
    if dest.exists():
        return dest
    print(f"  tải {url}")
    req = urllib.request.Request(url, data=data, headers={"User-Agent": "webgis-songhau/1.0"})
    with urllib.request.urlopen(req, timeout=600) as r:
        tmp = dest.with_suffix(".part")
        tmp.write_bytes(r.read())
        tmp.rename(dest)
    return dest


def get_json(url):
    req = urllib.request.Request(url, headers={"User-Agent": "webgis-songhau/1.0"})
    with urllib.request.urlopen(req, timeout=300) as r:
        return json.load(r)


def norm(name):
    s = unicodedata.normalize("NFC", name).lower()
    s = re.sub(r"^(tỉnh|thành phố)\s+", "", s)
    s = s.replace("hoà", "hòa").replace("–", "-").replace(" - ", "-").replace("thừa thiên huế", "huế")
    return s.strip()


def build_mapping():
    """63 tỉnh cũ (tên Natural Earth) → 34 tỉnh mới, từ API provinces.open-api.vn."""
    v1 = get_json(f"{PROVINCES_API}/v1/?depth=3")  # 63 tỉnh, xã trước sáp nhập
    v2 = get_json(f"{PROVINCES_API}/v2/?depth=2")  # 34 tỉnh, xã sau sáp nhập
    old_of_ward = {w["code"]: p["code"] for p in v1 for d in p["districts"] for w in d["wards"]}
    votes = defaultdict(Counter)
    for p in v2:
        for w in p["wards"]:
            if w["code"] in old_of_ward:
                votes[old_of_ward[w["code"]]][p["code"]] += 1
    new = {p["code"]: p["name"] for p in v2}
    old = {norm(p["name"]): (p["code"], p["name"]) for p in v1}
    if len(votes) != len(v1) or any(len(c) > 1 for c in votes.values()):
        raise SystemExit("Bảng đối chiếu tỉnh không nhất quán (tỉnh cũ chia cho nhiều tỉnh mới?) — kiểm tra lại API.")

    ne_csv = subprocess.run(["ogr2ogr", "-f", "CSV", "/vsistdout/", f"/vsizip/{CACHE / 'src_admin1.zip'}",
                             "-where", "adm0_a3='VNM'", "-select", "name,name_vi,iso_3166_2"],
                            capture_output=True, text=True, check=True).stdout
    rows = []
    for r in csv.DictReader(ne_csv.splitlines()):
        key = norm(NE_ISO_FIX.get(r["iso_3166_2"], r["name_vi"]))
        if key not in old:
            raise SystemExit(f"Không khớp tỉnh Natural Earth {r['name']} ({r['iso_3166_2']})")
        code, name = old.pop(key)
        n = votes[code].most_common(1)[0][0]
        rows.append({"ne_name": r["name"], "iso_3166_2": r["iso_3166_2"], "old_code": code, "old_name": name,
                     "new_code": n, "new_name": new[n]})
    if old:
        raise SystemExit(f"Tỉnh cũ không có trong Natural Earth: {sorted(old)}")
    rows.sort(key=lambda r: (r["new_code"], r["old_code"]))
    with MAPPING.open("w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0]))
        w.writeheader()
        w.writerows(rows)
    print(f"  {len(rows)} tỉnh cũ → {len({r['new_code'] for r in rows})} tỉnh mới -> {MAPPING.name}")


def sql_str(s):
    return "'" + s.replace("'", "''") + "'"


def build_tables():
    mapping = list(csv.DictReader(MAPPING.open(encoding="utf-8")))
    values = ",".join(f"({sql_str(r['ne_name'])},{int(r['new_code'])},{sql_str(r['new_name'])})" for r in mapping)
    islands = " union all ".join(
        f"select {sql_str(n)} as name, {sql_str(p)} as province, ST_MakeEnvelope({x0},{y0},{x1},{y1},4326) as box"
        for n, p, (x0, y0, x1, y1) in ARCHIPELAGOS)
    region = "ST_MakeEnvelope({},{},{},{},4326)".format(*REGION)
    psql(f"""
begin;
drop table if exists {SCHEMA}.ocean, {SCHEMA}.countries, {SCHEMA}.country_labels, {SCHEMA}.borders, {SCHEMA}.provinces,
  {SCHEMA}.province_labels, {SCHEMA}.archipelagos, {SCHEMA}.sea_labels, {SCHEMA}.cities, {SCHEMA}.ne_water,
  {SCHEMA}.ne_rivers, {SCHEMA}.osm_water, {SCHEMA}.osm_waterways, {SCHEMA}.osm_residential, {SCHEMA}.osm_roads,
  {SCHEMA}.osm_places cascade;

create table {SCHEMA}.ocean as select ogc_fid as id, geom from {SCHEMA}.src_ocean;
create table {SCHEMA}.countries as
  select ogc_fid as id, admin, name_vi, admin = 'Vietnam' as is_vn, geom from {SCHEMA}.src_countries;
create table {SCHEMA}.country_labels as
  select ogc_fid as id, coalesce(name_vi, admin) as name, labelrank::int as labelrank,
         ST_SetSRID(ST_MakePoint(label_x, label_y), 4326) as geom
  from {SCHEMA}.src_countries where label_x is not null and ST_Area(geom) > 0.1;  -- bỏ nhãn bãi đá/đảo lẻ
create table {SCHEMA}.borders as select ogc_fid as id, featurecla, geom from {SCHEMA}.src_borders;

create temp table map(ne_name text, code int, name text) on commit drop;
insert into map values {values};
create temp table isl on commit drop as
  select i.name, i.province, ST_Multi(ST_CollectionExtract(ST_Intersection(c.geom, i.box), 3)) as geom
  from ({islands}) i join {SCHEMA}.src_countries c on c.admin = 'Vietnam' and ST_Intersects(c.geom, i.box);
create table {SCHEMA}.provinces as
  select m.code as id, m.name, ST_Multi(ST_Union(g.geom)) as geom
  from (select a.name as ne_name, a.geom from {SCHEMA}.src_admin1 a where a.adm0_a3 = 'VNM'
        union all select m2.ne_name, isl.geom from isl join map m2 on m2.name = isl.province) g join map m on m.ne_name = g.ne_name
  group by m.code, m.name;
-- Nhãn tỉnh đặt trên phần đất liền lớn nhất (không rơi ra quần đảo).
create table {SCHEMA}.province_labels as
  select p.id, regexp_replace(p.name, '^(Tỉnh|Thành phố) ', '') as name,
         (select ST_PointOnSurface(d.geom) from ST_Dump(p.geom) d order by ST_Area(d.geom) desc limit 1) as geom
  from {SCHEMA}.provinces p;
create table {SCHEMA}.archipelagos as
  select row_number() over (order by name) as id, name, province, ST_Centroid(geom) as geom from isl;

create table {SCHEMA}.sea_labels as
  select ogc_fid as id, name_vi as name, scalerank::int as scalerank, featurecla,
         case when ST_Within(ST_Centroid(geom), geom) then ST_Centroid(geom) else ST_PointOnSurface(geom) end as geom
  from {SCHEMA}.src_marine where name_vi is not null and ST_Intersects(geom, {region});
-- Bỏ mọi địa danh nước khác đặt trên lãnh thổ/quần đảo của Việt Nam (vd "Sansha" trên đảo Phú Lâm).
-- Đô thị nước ngoài ghi tên Latin gốc: tên Hán Việt dễ gây nhầm (Changsha → "Trường Sa").
create table {SCHEMA}.cities as
  select ogc_fid as id, case when adm0_a3 = 'VNM' then coalesce(nullif(name_vi, ''), name) else name end as name,
         scalerank::int as scalerank, featurecla, adm0_a3, ST_GeometryN(geom, 1) as geom
  from {SCHEMA}.src_places p
  where ST_Intersects(geom, {region})
    and (adm0_a3 = 'VNM' or not exists (
         select 1 from {SCHEMA}.src_countries c where c.admin = 'Vietnam' and ST_Intersects(c.geom, p.geom))
       and not exists (select 1 from isl where ST_Intersects(ST_Envelope(ST_Expand(isl.geom, 0.5)), p.geom)));
create table {SCHEMA}.ne_water as select ogc_fid as id, scalerank::int as scalerank, geom
  from {SCHEMA}.src_lakes where ST_Intersects(geom, {region});
create table {SCHEMA}.ne_rivers as select ogc_fid as id, name, scalerank::int as scalerank, geom
  from {SCHEMA}.src_rivers where ST_Intersects(geom, {region}) and featurecla <> 'Lake Centerline';

create table {SCHEMA}.osm_water as select ogc_fid as id, name, geom from {SCHEMA}.src_osm_polygons
  where "natural" = 'water' or other_tags like '%"waterway"=>"riverbank"%';
create table {SCHEMA}.osm_residential as select ogc_fid as id, geom from {SCHEMA}.src_osm_polygons
  where landuse = 'residential';
create table {SCHEMA}.osm_waterways as select ogc_fid as id, name, waterway, geom from {SCHEMA}.src_osm_lines
  where waterway in ('river', 'canal');
create table {SCHEMA}.osm_roads as
  select ogc_fid as id, name, substring(other_tags from '"ref"=>"([^"]*)"') as ref, highway,
         case highway when 'motorway' then 1 when 'trunk' then 2 when 'primary' then 3 when 'secondary' then 4
                      when 'tertiary' then 5 else 6 end as rank, geom
  from {SCHEMA}.src_osm_lines where highway is not null;
create table {SCHEMA}.osm_places as select ogc_fid as id, name, place, ST_GeometryN(geom, 1) as geom
  from {SCHEMA}.src_osm_points where place is not null and name is not null;

do $$ declare t text; begin
  foreach t in array array['ocean','countries','country_labels','borders','provinces','province_labels','archipelagos',
      'sea_labels','cities','ne_water','ne_rivers','osm_water','osm_residential','osm_waterways','osm_roads','osm_places'] loop
    execute format('alter table {SCHEMA}.%I add primary key (id)', t);
    execute format('create index on {SCHEMA}.%I using gist (geom)', t);
  end loop;
end $$;
commit;
""")


def main():
    refresh = "--refresh" in sys.argv
    CACHE.mkdir(exist_ok=True)
    print("→ Natural Earth")
    for table, path in NE.items():
        download(NE_URL.format(path), CACHE / f"{table}.zip")
    print("→ OpenStreetMap (Overpass)")
    osm = CACHE / "osm_an_giang.osm"
    if refresh and osm.exists():
        osm.unlink()
    query = OSM_QUERY.format(b=",".join(map(str, OSM_BBOX)))
    download(OVERPASS, osm, urllib.parse.urlencode({"data": query}).encode())
    print("→ đối chiếu 63 → 34 tỉnh (provinces.open-api.vn)")
    if refresh or not MAPPING.exists():
        build_mapping()

    print("→ nạp PostGIS")
    psql(f"create schema if not exists {SCHEMA}")
    for table in NE:
        ogr_load(f"/vsizip/{CACHE / f'{table}.zip'}", table, "-lco", "FID=ogc_fid")
    for layer, table in (("points", "src_osm_points"), ("lines", "src_osm_lines"), ("multipolygons", "src_osm_polygons")):
        ogr_load(str(osm), table, layer, "-lco", "FID=ogc_fid", "-oo", "USE_CUSTOM_INDEXING=NO")
    build_tables()
    psql(f"""do $$ declare t text; begin
  for t in select tablename from pg_tables where schemaname = '{SCHEMA}' and tablename like 'src\\_%' loop
    execute format('drop table {SCHEMA}.%I', t);
  end loop; end $$;""")
    print(f"Xong: schema {SCHEMA}. Tiếp theo: geoserver/provision.sh songhau webgis_spatial")


if __name__ == "__main__":
    main()
