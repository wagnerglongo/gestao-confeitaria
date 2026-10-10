<?php

declare(strict_types=1);

use Confeitaria\App;
use Confeitaria\Database;

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/vendor/autoload.php';

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']),
    'use_strict_mode' => true,
]);

$app = new App(Database::connect(), $_SESSION, dirname(__DIR__) . '/templates');

[$status, $headers, $body] = $app->handle(
    $_SERVER['REQUEST_METHOD'],
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/',
    $_GET,
    $_POST,
    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
);

http_response_code($status);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; frame-ancestors 'none'");
foreach ($headers as $name => $value) {
    header("{$name}: {$value}");
}
echo $body;
