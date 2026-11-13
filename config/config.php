<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

function load_env(string $file): void
{
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $v = trim($v);
        if (strlen($v) >= 2 && ($v[0] === '"' || $v[0] === "'") && $v[-1] === $v[0]) {
            $v = substr($v, 1, -1);
        }
        $_ENV[trim($k)] = $v;
    }
}

function env(string $key, mixed $default = null): mixed
{
    return $_ENV[$key] ?? $default;
}

load_env(BASE_PATH . '/.env');
date_default_timezone_set((string) env('APP_TIMEZONE', 'Asia/Jakarta'));
