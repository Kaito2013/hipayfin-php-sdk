<?php
require_once __DIR__ . '/bootstrap.php';

$testFiles = glob(__DIR__ . '/*Test.php');
$total = 0;
$passed = 0;
$failed = 0;

echo "Running HiPayFin SDK Test Suite...\n\n";

foreach ($testFiles as $file) {
    $className = 'Lucas\\HiPayFin\\Tests\\' . basename($file, '.php');
    if (!class_exists($className)) {
        require_once $file;
    }
    if (!class_exists($className)) {
        continue;
    }

    $reflection = new ReflectionClass($className);
    $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC);
    $instance = new $className();

    foreach ($methods as $method) {
        if (strpos($method->name, 'test') === 0) {
            $total++;
            try {
                $method->invoke($instance);
                echo "  [PASS] {$className}::{$method->name}\n";
                $passed++;
            } catch (Exception $e) {
                echo "  [FAIL] {$className}::{$method->name} - " . $e->getMessage() . "\n";
                $failed++;
            }
        }
    }
}

echo "\nTests: {$total}, Passed: {$passed}, Failed: {$failed}\n";
exit($failed > 0 ? 1 : 0);
