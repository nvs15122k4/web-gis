<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_osm_residential</Name>
    <UserStyle>
      <Title>nen_osm_residential</Title>
      <FeatureTypeStyle>
        <Rule><MaxScaleDenominator>300000</MaxScaleDenominator>
          <PolygonSymbolizer><Fill><CssParameter name="fill">#e9e2d6</CssParameter></Fill></PolygonSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
