<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Migration PostGIS nằm riêng một thư mục, mỗi file tự khai báo $connection = 'pgsql'
        // nên `php artisan migrate` chạy được cả hai CSDL.
        $this->loadMigrationsFrom(database_path('migrations/pgsql'));
    }
}
