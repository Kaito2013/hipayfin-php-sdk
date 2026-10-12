<?php
namespace Lucas\HiPayFin\Tests;

class AutoloadTest
{
    public function testAutoloadCanLoadClasses()
    {
        require_once __DIR__ . '/../autoload.php';
        
        $classExists = class_exists('Lucas\\HiPayFin\\Config');
        if (!$classExists) {
            throw new \Exception('Failed to autoload Lucas\\HiPayFin\\Config class');
        }
        return true;
    }
}
