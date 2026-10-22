<?xml version="1.0" encoding="UTF-8"?>
<!-- SINH TỰ ĐỘNG từ resources/js/colors.json bằng scripts/build-colors.mjs — không sửa tay -->
<StyledLayerDescriptor version="1.0.0" xmlns="http://www.opengis.net/sld" xmlns:ogc="http://www.opengis.net/ogc" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <NamedLayer>
    <Name>shoreline</Name>
    <UserStyle>
      <Title>shoreline</Title>
      <FeatureTypeStyle>
        <Rule><LineSymbolizer><Stroke>
          <CssParameter name="stroke"><ogc:Function name="strConcat"><ogc:Literal>#</ogc:Literal><ogc:Function name="env"><ogc:Literal>color</ogc:Literal><ogc:Literal>1f6feb</ogc:Literal></ogc:Function></ogc:Function></CssParameter>
          <CssParameter name="stroke-width">2.5</CssParameter>
        </Stroke></LineSymbolizer></Rule>
      </FeatureTypeStyle>
    </UserStyle>
  </NamedLayer>
</StyledLayerDescriptor>
