<?php

/**
 * Điểm vào Laravel cho Vercel (runtime vercel-php). Hệ thống file của hàm chỉ ghi được ở /tmp,
 * nên storage và cache khởi động được dời sang đó; phiên/cache/log dùng driver không ghi file.
 */

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$tmp = sys_get_temp_dir();
foreach (['storage/app', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'] as $dir) {
    if (! is_dir("$tmp/$dir")) {
        mkdir("$tmp/$dir", 0775, true);
    }
}

// Laravel đọc các biến này khi dựng Application; đặt trước khi require bootstrap/app.php.
foreach ([
    'APP_SERVICES_CACHE' => "$tmp/bootstrap/cache/services.php",
    'APP_PACKAGES_CACHE' => "$tmp/bootstrap/cache/packages.php",
    'APP_CONFIG_CACHE' => "$tmp/bootstrap/cache/config.php",
    'APP_ROUTES_CACHE' => "$tmp/bootstrap/cache/routes-v7.php",
    'APP_EVENTS_CACHE' => "$tmp/bootstrap/cache/events.php",
    'VIEW_COMPILED_PATH' => "$tmp/storage/framework/views",
] as $key => $value) {
    putenv("$key=$value");
    $_ENV[$key] = $_SERVER[$key] = $value;
}

require __DIR__.'/../vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->useStoragePath("$tmp/storage");

// Hàm nằm ở /api/index.php nên Symfony tự coi "/api" là base URL và cắt khỏi đường dẫn,
// làm mọi route /api/* thành 404. Giả lập script ở gốc (như public/index.php).
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = dirname(__DIR__).'/public/index.php';

$app->handleRequest(Request::capture());
