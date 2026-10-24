<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_osm_places</Name>
    <UserStyle>
      <Title>nen_osm_places</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:Or><ogc:PropertyIsEqualTo><ogc:PropertyName>place</ogc:PropertyName><ogc:Literal>city</ogc:Literal></ogc:PropertyIsEqualTo><ogc:PropertyIsEqualTo><ogc:PropertyName>place</ogc:PropertyName><ogc:Literal>town</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Or></ogc:Filter><MaxScaleDenominator>1000000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">12</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">bold</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>2</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#2d2d2d</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>place</ogc:PropertyName><ogc:Literal>village</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>300000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">10</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">normal</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>1.8</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#3d3d3d</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:Or><ogc:PropertyIsEqualTo><ogc:PropertyName>place</ogc:PropertyName><ogc:Literal>hamlet</ogc:Literal></ogc:PropertyIsEqualTo><ogc:PropertyIsEqualTo><ogc:PropertyName>place</ogc:PropertyName><ogc:Literal>suburb</ogc:Literal></ogc:PropertyIsEqualTo><ogc:PropertyIsEqualTo><ogc:PropertyName>place</ogc:PropertyName><ogc:Literal>quarter</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Or></ogc:Filter><MaxScaleDenominator>70000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">9</CssParameter><CssParameter name="font-style">italic</CssParameter><CssParameter name="font-weight">normal</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>1.6</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#555555</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
