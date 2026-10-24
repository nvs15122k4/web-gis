<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_osm_roads</Name>
    <UserStyle>
      <Title>nen_osm_roads</Title>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>6</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>70000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#cfc7ba</CssParameter><CssParameter name="stroke-width">2.8</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>5</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>300000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#bdb4a6</CssParameter><CssParameter name="stroke-width">3.4</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>4</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>600000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#c8ac66</CssParameter><CssParameter name="stroke-width">3.8</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsLessThanOrEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>3</ogc:Literal></ogc:PropertyIsLessThanOrEqualTo></ogc:Filter><MaxScaleDenominator>2200000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#c98d4a</CssParameter><CssParameter name="stroke-width">4.4</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
      </FeatureTypeStyle>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>6</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>70000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#ffffff</CssParameter><CssParameter name="stroke-width">1.4</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>5</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>300000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#ffffff</CssParameter><CssParameter name="stroke-width">2</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>4</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter><MaxScaleDenominator>600000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#f9d98c</CssParameter><CssParameter name="stroke-width">2.4</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsLessThanOrEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>3</ogc:Literal></ogc:PropertyIsLessThanOrEqualTo></ogc:Filter><MaxScaleDenominator>2200000</MaxScaleDenominator>
          <LineSymbolizer><Stroke><CssParameter name="stroke">#f6b26b</CssParameter><CssParameter name="stroke-width">3</CssParameter><CssParameter name="stroke-linecap">round</CssParameter><CssParameter name="stroke-linejoin">round</CssParameter></Stroke></LineSymbolizer>
        </Rule>
      </FeatureTypeStyle>
      <FeatureTypeStyle>
        <Rule><ogc:Filter><ogc:Not><ogc:PropertyIsNull><ogc:PropertyName>ref</ogc:PropertyName></ogc:PropertyIsNull></ogc:Not></ogc:Filter><MaxScaleDenominator>300000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>ref</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">9</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">bold</CssParameter></Font><LabelPlacement><LinePlacement/></LabelPlacement><Halo><Radius>1.5</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#5b4a33</CssParameter></Fill><VendorOption name="followLine">true</VendorOption><VendorOption name="repeat">300</VendorOption><VendorOption name="maxAngleDelta">30</VendorOption><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
        <Rule><ogc:Filter><ogc:PropertyIsLessThanOrEqualTo><ogc:PropertyName>rank</ogc:PropertyName><ogc:Literal>5</ogc:Literal></ogc:PropertyIsLessThanOrEqualTo></ogc:Filter><MaxScaleDenominator>40000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">9</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">normal</CssParameter></Font><LabelPlacement><LinePlacement/></LabelPlacement><Halo><Radius>1.5</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#555555</CssParameter></Fill><VendorOption name="followLine">true</VendorOption><VendorOption name="repeat">300</VendorOption><VendorOption name="maxAngleDelta">30</VendorOption><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
