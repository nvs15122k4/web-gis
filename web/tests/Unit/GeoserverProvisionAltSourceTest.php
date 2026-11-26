<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** provision.sh phải tách bản chính khỏi bản đối chứng trong bảng shorelines (cột source). */
class GeoserverProvisionAltSourceTest extends TestCase
{
    private string $sh;

    protected function setUp(): void
    {
        $this->sh = file_get_contents(dirname(__DIR__, 2).'/geoserver/provision.sh');
    }

    public function test_script_is_valid_bash(): void
    {
        exec('bash -n '.escapeshellarg(dirname(__DIR__, 2).'/geoserver/provision.sh').' 2>&1', $out, $code);
        $this->assertSame(0, $code, implode("\n", $out));
    }

    public function test_main_layer_is_filtered_to_main_source(): void
    {
        $this->assertStringContainsString('cql "$STORE" shorelines "source = \'main\'"', $this->sh);
    }

    public function test_alt_layer_is_published_from_same_table_and_filtered_to_alt_sources(): void
    {
        $this->assertStringContainsString('shorelines_alt:shorelines:shoreline', $this->sh);
        $this->assertStringContainsString('cql "$STORE" shorelines_alt "source <> \'main\'"', $this->sh);
    }

    public function test_filters_are_applied_before_the_configuration_reload(): void
    {
        $this->assertLessThan(strpos($this->sh, 'rest/reload'), strpos($this->sh, 'cql "$STORE" shorelines '));
    }

    public function test_existing_layers_are_still_published(): void
    {
        foreach (['shorelines:shorelines:shoreline', 'transects:transects_styled:change_class', 'erosion_hotspots:erosion_hotspots:hotspot'] as $spec) {
            $this->assertStringContainsString($spec, $this->sh);
        }
    }
}
