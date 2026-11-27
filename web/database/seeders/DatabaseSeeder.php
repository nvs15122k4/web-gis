<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin mật khẩu mặc định chỉ để chạy test/E2E và máy phát triển; môi trường production không tạo.
        if (! app()->isProduction()) {
            User::updateOrCreate(
                ['email' => 'admin@webgis.test'],
                ['name' => 'Admin', 'password' => 'password', 'role' => 'admin']
            );
        }

        $this->call(DemoAccountsSeeder::class);
        $this->call(ThresholdConfigSeeder::class);
    }
}
