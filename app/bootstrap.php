<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config/config.php';
require BASE_PATH . '/config/database.php';

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
}

foreach (glob(BASE_PATH . '/app/helpers/*.php') as $f)    require_once $f;
foreach (glob(BASE_PATH . '/app/middleware/*.php') as $f) require_once $f;
require_once BASE_PATH . '/app/core/Router.php';

// Autoload class sederhana: controllers & services
spl_autoload_register(function (string $class): void {
    if (!preg_match('/^[A-Za-z0-9_]+$/', $class)) return;
    foreach (['controllers', 'services'] as $dir) {
        $file = BASE_PATH . "/app/$dir/$class.php";
        if (is_file($file)) { require $file; return; }
    }
});

// Error handling
$debug = env('APP_DEBUG', 'false') === 'true';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/app.log');

set_exception_handler(function (Throwable $e) use ($debug): void {
    error_log((string) $e);
    if ($debug) {
        http_response_code(500);
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    abort(500, 'Terjadi kesalahan pada server.');
});

if (PHP_SAPI !== 'cli') {
    session_name('KOSTSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => env('APP_ENV') === 'production',
    ]);
    session_start();

    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
