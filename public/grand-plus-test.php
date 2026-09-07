<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo '<pre>';

echo "PHP OK\n";
echo "VERSION: " . PHP_VERSION . "\n";
echo "__DIR__: " . __DIR__ . "\n";

$configFile = __DIR__ . '/vitrine-mail-config.php';

echo "CONFIG: " . $configFile . "\n";
echo "CONFIG EXISTS: " . (file_exists($configFile) ? 'OUI' : 'NON') . "\n";

if (file_exists($configFile)) {

    echo "REQUIRE CONFIG...\n";

    $config = require $configFile;

    echo "CONFIG REQUIRE OK\n";
    echo "CONFIG TYPE: " . gettype($config) . "\n";

    if (is_array($config)) {
        echo "ADMIN USER: " . (isset($config['grand_plus_admin_user']) ? 'OUI' : 'NON') . "\n";
        echo "ADMIN PASSWORD: " . (isset($config['grand_plus_admin_password']) ? 'OUI' : 'NON') . "\n";
        echo "UNSUBSCRIBE SECRET: " . (isset($config['grand_plus_unsubscribe_secret']) ? 'OUI' : 'NON') . "\n";
    }
}

echo "</pre>";