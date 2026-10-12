<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Support\Uuid;

class UuidTest
{
    public function testGenerateV4Uuid()
    {
        $uuid1 = Uuid::v4();
        $uuid2 = Uuid::v4();

        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, $uuid1)) {
            throw new \Exception("UUID1 {$uuid1} is not valid RFC 4122 v4");
        }
        if (!preg_match($pattern, $uuid2)) {
            throw new \Exception("UUID2 {$uuid2} is not valid RFC 4122 v4");
        }
        if ($uuid1 === $uuid2) {
            throw new \Exception('UUIDs must be unique');
        }
        return true;
    }
}
