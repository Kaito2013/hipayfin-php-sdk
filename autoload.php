<?php
/**
 * Standalone PSR-4 Autoloader for HiPayFin PHP SDK
 * Suitable for environments without Composer (PHP 5.6+).
 */
spl_autoload_register(function ($class) {
    $prefix = 'Lucas\\HiPayFin\\';
    $baseDir = __DIR__ . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
