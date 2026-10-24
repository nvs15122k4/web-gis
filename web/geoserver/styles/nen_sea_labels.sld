<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_sea_labels</Name>
    <UserStyle>
      <Title>nen_sea_labels</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>name</ogc:PropertyName><ogc:Literal>Biển Đông</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MinScaleDenominator>1000000</MinScaleDenominator><MaxScaleDenominator>60000000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">15</CssParameter><CssParameter name="font-style">italic</CssParameter><CssParameter name="font-weight">bold</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>30</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>0</Radius><Fill><CssParameter name="fill">#aad3df</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#3d6f96</CssParameter></Fill><VendorOption name="autoWrap">120</VendorOption><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:And><ogc:PropertyIsNotEqualTo><ogc:PropertyName>name</ogc:PropertyName><ogc:Literal>Biển Đông</ogc:Literal></ogc:PropertyIsNotEqualTo><ogc:PropertyIsLessThanOrEqualTo><ogc:PropertyName>scalerank</ogc:PropertyName><ogc:Literal>3</ogc:Literal></ogc:PropertyIsLessThanOrEqualTo></ogc:And></ogc:Filter><MinScaleDenominator>1000000</MinScaleDenominator><MaxScaleDenominator>40000000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">11</CssParameter><CssParameter name="font-style">italic</CssParameter><CssParameter name="font-weight">normal</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>0</Radius><Fill><CssParameter name="fill">#aad3df</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#4f83a8</CssParameter></Fill><VendorOption name="autoWrap">100</VendorOption><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
