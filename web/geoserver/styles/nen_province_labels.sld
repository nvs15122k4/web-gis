<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_province_labels</Name>
    <UserStyle>
      <Title>nen_province_labels</Title>
      <FeatureTypeStyle>
        <Rule><MinScaleDenominator>150000</MinScaleDenominator><MaxScaleDenominator>12000000</MaxScaleDenominator>
          <TextSymbolizer><Label><ogc:PropertyName>name</ogc:PropertyName></Label><Font><CssParameter name="font-family">DejaVu Sans</CssParameter><CssParameter name="font-size">10</CssParameter><CssParameter name="font-style">normal</CssParameter><CssParameter name="font-weight">normal</CssParameter></Font><LabelPlacement><PointPlacement><AnchorPoint><AnchorPointX>0.5</AnchorPointX><AnchorPointY>0.5</AnchorPointY></AnchorPoint><Displacement><DisplacementX>0</DisplacementX><DisplacementY>0</DisplacementY></Displacement></PointPlacement></LabelPlacement><Halo><Radius>1.6</Radius><Fill><CssParameter name="fill">#ffffff</CssParameter></Fill></Halo><Fill><CssParameter name="fill">#6f6480</CssParameter></Fill><VendorOption name="spaceAround">4</VendorOption><VendorOption name="partials">false</VendorOption></TextSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
