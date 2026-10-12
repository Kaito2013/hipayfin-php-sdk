<?php
namespace Lucas\HiPayFin\Support;

use Lucas\HiPayFin\Exceptions\HiPayFinValidationException;

class Currency
{
    const CURRENCY_VND  = 'VND';
    const CURRENCY_KRW  = 'KRW';
    const CURRENCY_USDT = 'USDT';

    /**
     * Convert human readable amount to integer string Minor Unit.
     *
     * @param int|float|string $amount
     * @param string $currency
     * @return string
     * @throws HiPayFinValidationException
     */
    public static function toMinorUnit($amount, $currency)
    {
        $currency = strtoupper(trim((string)$currency));

        if ($currency === self::CURRENCY_VND || $currency === self::CURRENCY_KRW) {
            $cleaned = preg_replace('/[^0-9]/', '', (string)$amount);
            $cleaned = ltrim($cleaned, '0');
            if ($cleaned === '') {
                throw new HiPayFinValidationException("Invalid {$currency} amount: {$amount}", 'amount');
            }
            return $cleaned;
        }

        if ($currency === self::CURRENCY_USDT) {
            $amountStr = trim((string)$amount);
            if (!preg_match('/^[0-9]+(\.[0-9]{1,6})?$/', $amountStr)) {
                throw new HiPayFinValidationException("Invalid USDT amount: {$amount}", 'amount');
            }

            $parts = explode('.', $amountStr);
            $integerPart = $parts[0];
            $decimalPart = isset($parts[1]) ? $parts[1] : '';
            $decimalPart = str_pad($decimalPart, 6, '0', STR_PAD_RIGHT);

            $result = ltrim($integerPart . $decimalPart, '0');
            if ($result === '') {
                throw new HiPayFinValidationException("USDT amount must be greater than 0", 'amount');
            }
            return $result;
        }

        throw new HiPayFinValidationException("Unsupported currency: {$currency}", 'currency');
    }

    /**
     * Validate whether a string is a valid Minor Unit integer string.
     *
     * @param string $minorAmount
     * @return bool
     */
    public static function validateMinorUnit($minorAmount)
    {
        return (bool)preg_match('/^[1-9][0-9]*$/', (string)$minorAmount);
    }
}
