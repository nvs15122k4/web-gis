<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_osm_waterways</Name>
    <UserStyle>
      <Title>nen_osm_waterways</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>waterway</ogc:PropertyName><ogc:Literal>river</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>1000000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#aad3df</CssParameter><CssParameter name="stroke-width">2</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>waterway</ogc:PropertyName><ogc:Literal>canal</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>300000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#aad3df</CssParameter><CssParameter name="stroke-width">1.2</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><MaxScaleDenominator>70000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">10</CssParameter><CssParameter name="font-style">italic</CssParameter><CssParameter name="font-weight">normal</CssParameter></Font><LabelPlacement><LinePlacement/></LabelPlacement><Halo><Radius>1.5</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#3f7ea6</CssParameter></Fill><VendorOption name="followLine">true</VendorOption><VendorOption name="repeat">300</VendorOption><VendorOption name="maxAngleDelta">30</VendorOption><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
