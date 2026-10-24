<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_provinces</Name>
    <UserStyle>
      <Title>nen_provinces</Title>
      <FeatureTypeStyle>
        <Rule><MinScaleDenominator>150000</MinScaleDenominator><MaxScaleDenominator>20000000</MaxScaleDenominator>
          <PolygonSymbolizer><Stroke><CssParameter name="stroke">#a497b0</CssParameter><CssParameter name="stroke-width">0.8</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter><CssParameter name="stroke-dasharray">6 3</CssParameter></Stroke></PolygonSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
