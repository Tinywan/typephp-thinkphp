<?php
use think\App;

function main() :void
{
    require __DIR__ . '/vendor/autoload.php';
    global $argv;
    $cmd = $argv[1] ?? '';
    if ($cmd === 'info') {
        echo "PHP_BINARY: " . PHP_BINARY . "\n";
        echo "PHP_VERSION: " . PHP_VERSION . "\n";
        echo "PHP_SAPI: " . PHP_SAPI . "\n";
        echo "__DIR__: " . __DIR__ . "\n";
        echo "php_ini_loaded_file: " . (php_ini_loaded_file() ?: '(none)') . "\n";
        echo "php_ini_scanned_files: " . (php_ini_scanned_files() ?: '(none)') . "\n";
        echo "extension_dir: " . ini_get('extension_dir') . "\n";
        echo "get_loaded_extensions: " . implode(', ', get_loaded_extensions()) . "\n";
        echo "extension_loaded(pdo): " . (extension_loaded('pdo') ? 'yes' : 'no') . "\n";
        echo "extension_loaded(pdo_mysql): " . (extension_loaded('pdo_mysql') ? 'yes' : 'no') . "\n";
        return;
    }

    $app = new App();
    $app->make('console')->run();
}
