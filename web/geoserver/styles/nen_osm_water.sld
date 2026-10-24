<?xml version="1.0" encoding="UTF-8"?>
<!-- Nền bản đồ tự phục vụ (schema basemap, xem geoserver/basemap/build_basemap.py) -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>nen_osm_water</Name>
    <UserStyle>
      <Title>nen_osm_water</Title>
      <FeatureTypeStyle>
        <Rule><MaxScaleDenominator>1000000</MaxScaleDenominator>
          <PolygonSymbolizer><Fill><CssParameter name="fill">#aad3df</CssParameter></Fill></PolygonSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
