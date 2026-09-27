<?php

namespace Database\Seeders;

use App\Models\ThresholdConfig;
use Illuminate\Database\Seeder;

class ThresholdConfigSeeder extends Seeder
{
    public function run(): void
    {
        ThresholdConfig::updateOrCreate(
            ['key' => 'min_erosion_rate'],
            ['value' => '1.0', 'description' => 'Tốc độ lùi bờ tối thiểu (m/năm) để hiển thị hotspot trên UI']
        );
    }
}
