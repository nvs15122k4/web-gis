<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_countries</Name>
    <UserStyle>
      <Title>nen_countries</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>is_vn</ogc:PropertyName><ogc:Literal>false</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <PolygonSymbolizer><Fill><CssParameter name="fill">#efebe3</CssParameter></Fill></PolygonSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>is_vn</ogc:PropertyName><ogc:Literal>true</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <PolygonSymbolizer><Fill><CssParameter name="fill">#fbf8f1</CssParameter></Fill><Stroke><CssParameter name="stroke">#b9a98a</CssParameter><CssParameter name="stroke-width">0.6</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></PolygonSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
