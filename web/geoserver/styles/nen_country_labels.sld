<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_country_labels</Name>
    <UserStyle>
      <Title>nen_country_labels</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsLessThanOrEqualTo><ogc:PropertyName>labelrank</ogc:PropertyName><ogc:Literal>3</ogc:Literal></ogc:PropertyIsLessThanOrEqualTo></ogc:Filter><MinScaleDenominator>4000000</MinScaleDenominator><MaxScaleDenominator>80000000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">13</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">bold</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>2</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#5d5450</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsGreaterThan><ogc:PropertyName>labelrank</ogc:PropertyName><ogc:Literal>3</ogc:Literal></ogc:PropertyIsGreaterThan></ogc:Filter><MinScaleDenominator>4000000</MinScaleDenominator><MaxScaleDenominator>20000000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">11</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">bold</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>2</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#6d6460</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
