<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_ne_rivers</Name>
    <UserStyle>
      <Title>nen_ne_rivers</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsLessThanOrEqualTo><ogc:PropertyName>scalerank</ogc:PropertyName><ogc:Literal>4</ogc:Literal></ogc:PropertyIsLessThanOrEqualTo></ogc:Filter><MinScaleDenominator>1000000</MinScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#aad3df</CssParameter><CssParameter name="stroke-width">1.6</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsGreaterThan><ogc:PropertyName>scalerank</ogc:PropertyName><ogc:Literal>4</ogc:Literal></ogc:PropertyIsGreaterThan></ogc:Filter><MinScaleDenominator>1000000</MinScaleDenominator><MaxScaleDenominator>8000000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#aad3df</CssParameter><CssParameter name="stroke-width">0.8</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
