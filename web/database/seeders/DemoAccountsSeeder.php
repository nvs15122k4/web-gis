<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Tạo hai tài khoản demo (quản trị + người xem) từ biến môi trường, để mật khẩu không nằm trong mã nguồn công khai.
 * Biến nào thiếu thì bỏ qua tài khoản đó.
 *
 *   DEMO_ADMIN_EMAIL / DEMO_ADMIN_PASSWORD   → vai trò admin
 *   DEMO_USER_EMAIL  / DEMO_USER_PASSWORD    → vai trò viewer
 */
class DemoAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['DEMO_ADMIN', 'Quản trị demo', 'admin'],
            ['DEMO_USER', 'Người dùng demo', 'viewer'],
        ] as [$prefix, $name, $role]) {
            $email = env("{$prefix}_EMAIL");
            $password = env("{$prefix}_PASSWORD");
            if (! $email || ! $password) {
                continue;
            }
            User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'role' => $role]);
        }
    }
}
