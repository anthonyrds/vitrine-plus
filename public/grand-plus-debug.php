<?php

header('Content-Type: text/plain; charset=utf-8');

echo "=== GRAND+ DEBUG ===\n\n";

echo "PHP_VERSION: ";
echo PHP_VERSION;
echo "\n\n";

echo "__DIR__: ";
echo __DIR__;
echo "\n\n";

echo "CONFIG attendu avec __DIR__: ";
echo __DIR__ . '/vitrine-mail-config.php';
echo "\n";

echo "CONFIG attendu avec dirname(__DIR__): ";
echo dirname(__DIR__) . '/vitrine-mail-config.php';
echo "\n\n";

$configPath1 = __DIR__ . '/vitrine-mail-config.php';
$configPath2 = dirname(__DIR__) . '/vitrine-mail-config.php';

echo "EXISTS config __DIR__: ";
echo file_exists($configPath1) ? 'OUI' : 'NON';
echo "\n";

echo "EXISTS config dirname(__DIR__): ";
echo file_exists($configPath2) ? 'OUI' : 'NON';
echo "\n\n";

foreach ([$configPath1, $configPath2] as $path) {
    if (file_exists($path)) {
        echo "TEST CONFIG: ";
        echo $path;
        echo "\n";

        try {
            $config = require $path;

            echo "REQUIRE: OK\n";
            echo "CONFIG ARRAY: ";
            echo is_array($config) ? 'OUI' : 'NON';
            echo "\n";

            if (is_array($config)) {
                echo "ADMIN USER PRESENT: ";
                echo isset($config['grand_plus_admin_user']) ? 'OUI' : 'NON';
                echo "\n";

                echo "ADMIN PASSWORD PRESENT: ";
                echo !empty($config['grand_plus_admin_password']) ? 'OUI' : 'NON';
                echo "\n";

                echo "UNSUBSCRIBE SECRET PRESENT: ";
                echo !empty($config['grand_plus_unsubscribe_secret']) ? 'OUI' : 'NON';
                echo "\n";
            }
        } catch (Throwable $e) {
            echo "REQUIRE ERROR: ";
            echo $e->getMessage();
            echo "\n";
        }

        echo "\n";
    }
}

echo "=== FIN DEBUG ===\n";