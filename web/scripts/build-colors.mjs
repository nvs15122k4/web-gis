// Sinh CSS variable và SLD GeoServer từ MỘT nguồn màu duy nhất: resources/js/colors.json (Mục 10 kế hoạch).
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const colors = JSON.parse(readFileSync('resources/js/colors.json', 'utf8'));
const dash = (k) => k.replaceAll('_', '-');

writeFileSync(
    'resources/css/colors.css',
    `/* SINH TỰ ĐỘNG từ resources/js/colors.json bằng scripts/build-colors.mjs — không sửa tay */\n:root {\n${Object.entries(colors)
        .map(([k, v]) => `    --c-${dash(k)}: ${v};`)
        .join('\n')}\n}\n`,
);

const rule = (k, v) => `        <Rule>
          <Name>${k}</Name>
          <Title>${k}</Title>
          <ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>change_class</ogc:PropertyName><ogc:Literal>${k}</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <LineSymbolizer><Stroke>
            <CssParameter name="stroke">${v}</CssParameter>
            <CssParameter name="stroke-width">3</CssParameter>
          </Stroke></LineSymbolizer>
        </Rule>`;

const sld = (name, rules) => `<?xml version="1.0" encoding="UTF-8"?>
<!-- SINH TỰ ĐỘNG từ resources/js/colors.json bằng scripts/build-colors.mjs — không sửa tay -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>${name}</Name>
    <UserStyle>
      <Title>${name}</Title>
      <FeatureTypeStyle>
${rules}
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
`;

mkdirSync('geoserver/styles', { recursive: true });
writeFileSync('geoserver/styles/change_class.sld', sld('change_class', Object.entries(colors).map(([k, v]) => rule(k, v)).join('\n')));

// Đường bờ theo năm: màu truyền qua tham số env=color:rrggbb (mặc định xanh).
writeFileSync('geoserver/styles/shoreline.sld', sld('shoreline', `        <Rule><LineSymbolizer><Stroke>
          <CssParameter name="stroke"><ogc:Function name="strConcat"><ogc:Literal>#</ogc:Literal><ogc:Function name="env"><ogc:Literal>color</ogc:Literal><ogc:Literal>1f6feb</ogc:Literal></ogc:Function></ogc:Function></CssParameter>
          <CssParameter name="stroke-width">2.5</CssParameter>
        </Stroke></LineSymbolizer></Rule>`));

// Các lớp còn lại dùng style đơn sắc.
writeFileSync('geoserver/styles/hotspot.sld', sld('hotspot', `        <Rule><LineSymbolizer><Stroke>
          <CssParameter name="stroke">${colors.severe_erosion}</CssParameter>
          <CssParameter name="stroke-width">6</CssParameter>
        </Stroke></LineSymbolizer></Rule>`));
writeFileSync('geoserver/styles/reference.sld', sld('reference', `        <Rule><PointSymbolizer><Graphic>
          <Mark><WellKnownName>circle</WellKnownName><Fill><CssParameter name="fill">#7b3fe4</CssParameter></Fill></Mark>
          <Size>10</Size>
        </Graphic></PointSymbolizer></Rule>`));
console.log('colors.css + geoserver/styles/*.sld đã được sinh.');
