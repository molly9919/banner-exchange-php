<?php

declare(strict_types=1);

session_start();

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    header('Location: /install.php');
    exit;
}

$config = require $configFile;

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require $path;
    }
});

$db = new App\Database($config['db']);
$exchange = new App\BannerExchange($db, $config);
