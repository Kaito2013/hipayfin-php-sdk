<?php
namespace Lucas\HiPayFin\Tests;

use Lucas\HiPayFin\Support\Currency;

class CurrencyTest
{
    public function testVndAndKrwMinorUnit()
    {
        if (Currency::toMinorUnit(150000, 'VND') !== '150000') {
            throw new \Exception('VND integer conversion failed');
        }
        if (Currency::toMinorUnit('250000', 'vnd') !== '250000') {
            throw new \Exception('VND string conversion failed');
        }
        if (Currency::toMinorUnit(50000, 'KRW') !== '50000') {
            throw new \Exception('KRW conversion failed');
        }
        return true;
    }

    public function testUsdtMinorUnitPrecision()
    {
        if (Currency::toMinorUnit(10, 'USDT') !== '10000000') {
            throw new \Exception('USDT 10 conversion failed');
        }
        if (Currency::toMinorUnit('1.5', 'usdt') !== '1500000') {
            throw new \Exception('USDT 1.5 conversion failed');
        }
        if (Currency::toMinorUnit('0.000001', 'USDT') !== '1') {
            throw new \Exception('USDT 0.000001 conversion failed');
        }
        if (Currency::toMinorUnit('19.99', 'USDT') !== '19990000') {
            throw new \Exception('USDT 19.99 conversion failed');
        }
        return true;
    }

    public function testValidateMinorUnit()
    {
        if (!Currency::validateMinorUnit('150000')) {
            throw new \Exception('Valid minor unit rejected');
        }
        if (Currency::validateMinorUnit('0150000')) {
            throw new \Exception('Leading zero should be invalid');
        }
        if (Currency::validateMinorUnit('150.50')) {
            throw new \Exception('Decimal point should be invalid');
        }
        if (Currency::validateMinorUnit('0')) {
            throw new \Exception('Zero should be invalid');
        }
        if (Currency::validateMinorUnit('-100')) {
            throw new \Exception('Negative should be invalid');
        }
        return true;
    }
}
