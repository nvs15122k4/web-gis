<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Tài khoản admin mặc định cho môi trường phát triển — đổi mật khẩu khi triển khai thật.
        User::updateOrCreate(
            ['email' => 'admin@webgis.test'],
            ['name' => 'Admin', 'password' => 'password', 'role' => 'admin']
        );

        $this->call(ThresholdConfigSeeder::class);
    }
}
