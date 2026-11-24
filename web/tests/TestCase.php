<?php

namespace Tests;

use Database\Seeders\E2eFixtureSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Schema đã migrate sẵn (php artisan migrate --env=testing); mỗi test chạy trong transaction rồi rollback. */
    protected array $connectionsToTransact = ['mysql', 'pgsql'];

    protected function setUp(): void
    {
        parent::setUp();

        // Chế độ một CSDL (DB_CONNECTION=pgsql, như Supabase): chỉ còn kết nối pgsql, không có MySQL.
        if (config('database.default') === 'pgsql') {
            $this->connectionsToTransact = ['pgsql'];
        }

        foreach ($this->connectionsToTransact as $name) {
            $this->app['db']->connection($name)->beginTransaction();
        }
        $this->beforeApplicationDestroyed(function () {
            foreach ($this->connectionsToTransact as $name) {
                $this->app['db']->connection($name)->rollBack();
            }
        });

        $this->seed(E2eFixtureSeeder::class);
    }
}
