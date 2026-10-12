<?php
namespace Lucas\HiPayFin\Support;

class Uuid
{
    /**
     * Generate RFC 4122 compliant UUID version 4.
     *
     * @return string
     */
    public static function v4()
    {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $data = openssl_random_pseudo_bytes(16);
        } else {
            $data = '';
            for ($i = 0; $i < 16; $i++) {
                $data .= chr(mt_rand(0, 255));
            }
        }

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100 (v4)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
