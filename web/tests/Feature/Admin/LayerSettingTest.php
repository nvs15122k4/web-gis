<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\LayerSetting;
use App\Models\User;
use Tests\TestCase;

class LayerSettingTest extends TestCase
{
    private function admin(): User
    {
        return User::where('email', 'admin@webgis.test')->firstOrFail();
    }

    public function test_public_read_returns_all_layers_with_defaults(): void
    {
        $r = $this->getJson('/api/config/layers')->assertOk();
        $this->assertSame(['transects', 'rates', 'hotspots', 'reference'], array_column($r->json(), 'key'));
        $this->assertSame(100, $r->json('0.opacity'));
    }

    public function test_admin_can_update_and_value_is_persisted_and_logged(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->putJson('/api/admin/config/layers/hotspots', ['visible' => false, 'opacity' => 60, 'sort_order' => 9])
            ->assertOk()->assertJsonPath('visible', false)->assertJsonPath('opacity', 60);

        $row = collect($this->getJson('/api/config/layers')->json())->firstWhere('key', 'hotspots');
        $this->assertSame(60, $row['opacity']);
        $this->assertFalse($row['visible']);
        $this->assertSame(9, $row['sort_order']);
        $this->assertSame(1, ActivityLog::where('action', 'like', 'update_layer:hotspots%')->count());
    }

    public function test_partial_update_keeps_other_fields(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/config/layers/transects', ['opacity' => 40])->assertOk();
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/config/layers/transects', ['visible' => false])->assertOk()->assertJsonPath('opacity', 40);
    }

    public static function invalid(): array
    {
        return [[['opacity' => 5]], [['opacity' => 101]], [['opacity' => 'abc']], [['visible' => 'maybe']], [['sort_order' => -1]]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalid')]
    public function test_invalid_input_is_rejected(array $body): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/config/layers/transects', $body)->assertStatus(422);
        $this->assertSame(0, LayerSetting::count());
    }

    public function test_unknown_layer_is_404(): void
    {
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/config/layers/..%2Fx', ['opacity' => 50])->assertNotFound();
        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/config/layers/nope', ['opacity' => 50])->assertNotFound();
    }

    public function test_guest_cannot_update(): void
    {
        $this->putJson('/api/admin/config/layers/transects', ['opacity' => 50])->assertUnauthorized();
    }
}
