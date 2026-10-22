<?xml version="1.0" encoding="UTF-8"?>
<!-- SINH TỰ ĐỘNG từ resources/js/colors.json bằng scripts/build-colors.mjs — không sửa tay -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>change_class</Name>
    <UserStyle>
      <Title>change_class</Title>
      <FeatureTypeStyle>
        <Rule>
          <Name>accretion</Name>
          <Title>accretion</Title>
          <ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>change_class</ogc:PropertyName><ogc:Literal>accretion</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <LineSymbolizer><Stroke>
            <CssParameter name="stroke">#2e9d4f</CssParameter>
            <CssParameter name="stroke-width">3</CssParameter>
          </Stroke></LineSymbolizer>
        </Rule>
        <Rule>
          <Name>stable</Name>
          <Title>stable</Title>
          <ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>change_class</ogc:PropertyName><ogc:Literal>stable</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <LineSymbolizer><Stroke>
            <CssParameter name="stroke">#8a8a8a</CssParameter>
            <CssParameter name="stroke-width">3</CssParameter>
          </Stroke></LineSymbolizer>
        </Rule>
        <Rule>
          <Name>mild_erosion</Name>
          <Title>mild_erosion</Title>
          <ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>change_class</ogc:PropertyName><ogc:Literal>mild_erosion</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <LineSymbolizer><Stroke>
            <CssParameter name="stroke">#b8862b</CssParameter>
            <CssParameter name="stroke-width">3</CssParameter>
          </Stroke></LineSymbolizer>
        </Rule>
        <Rule>
          <Name>severe_erosion</Name>
          <Title>severe_erosion</Title>
          <ogc:Filter><ogc:PropertyIsEqualTo><ogc:PropertyName>change_class</ogc:PropertyName><ogc:Literal>severe_erosion</ogc:Literal></ogc:PropertyIsEqualTo></ogc:Filter>
          <LineSymbolizer><Stroke>
            <CssParameter name="stroke">#d0312d</CssParameter>
            <CssParameter name="stroke-width">3</CssParameter>
          </Stroke></LineSymbolizer>
        </Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
