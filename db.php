<?php

function connectDatabase(string $database, string $charset = 'utf8mb4'): PDO
{
    $configFile = __DIR__ . '/config.local.php';
    if (!is_file($configFile)) {
        throw new RuntimeException('Missing config.local.php. Copy config.local.php.example and add the database credentials.');
    }

    $config = require $configFile;
    foreach (['host', 'user', 'password'] as $key) {
        if (!isset($config[$key]) || $config[$key] === '') {
            throw new RuntimeException("Missing database setting: {$key}");
        }
    }

    return new PDO(
        "mysql:host={$config['host']};dbname={$database};charset={$charset}",
        $config['user'],
        $config['password'],
        [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}",
        ]
    );
}
