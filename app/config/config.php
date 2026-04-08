<?php

declare(strict_types=1);

if (!function_exists('env')) {
    function env(string $key, ?string $default = null): ?string {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        return $v;
    }
}

return [
    'db' => [
        'host' => env('DB_HOST', 'db-primary'),
        'replica_host' => env('DB_REPLICA_HOST', 'db-replica'),
        'port' => (int) env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'appdb'),
        'user' => env('DB_USER', 'appuser'),
        'pass' => env('DB_PASSWORD', ''),
    ],
    'redis' => [
        'host' => env('REDIS_HOST', 'redis'),
        'port' => (int) env('REDIS_PORT', '6379'),
    ],
    'smtp' => [
        'host' => env('SMTP_HOST', 'mailpit'),
        'port' => (int) env('SMTP_PORT', '1025'),
        'from' => env('SMTP_FROM', 'noreply@example.com'),
    ],
    'app' => [
        'base_url' => rtrim(env('APP_BASE_URL', 'http://localhost:8080'), '/'),
    ],
];
