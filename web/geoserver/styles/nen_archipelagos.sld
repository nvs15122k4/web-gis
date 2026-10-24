<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_archipelagos</Name>
    <UserStyle>
      <Title>nen_archipelagos</Title>
      <FeatureTypeStyle>
        <Rule><MinScaleDenominator>150000</MinScaleDenominator><MaxScaleDenominator>60000000</MaxScaleDenominator>
          <PointSymbolizer><Graphic><Mark><WellKnownName>circle</WellKnownName><Fill><CssParameter name="fill">#c0392b</CssParameter></Fill></Mark><Size>4</Size></Graphic></PointSymbolizer><TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName>
(Việt Nam)</Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">11</CssParameter><CssParameter name="font-style">italic</CssParameter><CssParameter name="font-weight">bold</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>1</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>-6</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>1.2</Radius><Fill><CssParameter name="fill">#aad3df</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#1f3f66</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption><VendorOption name="conflictResolution">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
