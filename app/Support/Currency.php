<?php

namespace App\Support;

final class Currency
{
    /** @var array<string,string> */
    private static array $symbolMap = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'JPY' => '¥',
        'CNY' => '¥',
        'CAD' => '$',
        'AUD' => '$',
        'NZD' => '$',
        'CHF' => 'CHF',
        'SEK' => 'kr',
        'NOK' => 'kr',
        'DKK' => 'kr',
        'AED' => 'د.إ',
        'SAR' => 'ر.س',
        'QAR' => 'ر.ق',
        'KWD' => 'د.ك',
        'BHD' => '.د.ب',
        'INR' => '₹',
        'PKR' => '₨',
        'BDT' => '৳',
        'LKR' => 'Rs',
        'HKD' => '$',
        'SGD' => '$',
        'THB' => '฿',
        'IDR' => 'Rp',
        'KRW' => '₩',
        'TWD' => 'NT$',
        'ZAR' => 'R',
        'BRL' => 'R$',
        'MXN' => '$',
        'ARS' => '$',
        'CLP' => '$',
        'COP' => '$',
        'PEN' => 'S/',
        'RUB' => '₽',
        'TRY' => '₺',
        'PLN' => 'zł',
        'CZK' => 'Kč',
        'HUF' => 'Ft',
        'RON' => 'lei',
        'ILS' => '₪',
        'EGP' => 'E£',
        'NGN' => '₦',
        'KES' => 'KSh',
        // add more as needed
    ];

    /**
     * Return a symbol for a 3-letter ISO code. If unknown, return the ISO code
     * itself (e.g., "USD") – NEVER default to a random currency like PKR.
     */
    public static function symbol(?string $iso): string
    {
        $code = strtoupper((string) $iso);
        if (isset(self::$symbolMap[$code])) {
            return self::$symbolMap[$code];
        }
        // Safe fallback: show the code, not another currency’s symbol
        return $code ?: 'USD';
    }
}
