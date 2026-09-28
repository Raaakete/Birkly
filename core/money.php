<?php
/**
 * One money formatter for the admin (Birch spec §12).
 *
 * Money is passed as integer minor units and never rounded or abbreviated.
 * A symbol is shown only when it names one currency; shared symbols ($, kr, ¥)
 * and views that mix currencies show the ISO code. admin/js/money.js mirrors
 * this for the browser.
 */

/** ISO 4217 minor units that differ from 2. */
function birkly_money_minor_digits(string $currency): int
{
    static $digits = [
        'BIF' => 0, 'CLP' => 0, 'DJF' => 0, 'GNF' => 0, 'ISK' => 0, 'JPY' => 0, 'KMF' => 0, 'KRW' => 0,
        'PYG' => 0, 'RWF' => 0, 'UGX' => 0, 'UYI' => 0, 'VND' => 0, 'VUV' => 0, 'XAF' => 0, 'XOF' => 0, 'XPF' => 0,
        'BHD' => 3, 'IQD' => 3, 'JOD' => 3, 'KWD' => 3, 'LYD' => 3, 'OMR' => 3, 'TND' => 3,
    ];
    return $digits[strtoupper($currency)] ?? 2;
}

/** Symbols that name exactly one currency. Anything else shows its code. */
function birkly_money_unambiguous_symbols(): array
{
    return ['EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'KRW' => '₩', 'ILS' => '₪', 'TRY' => '₺', 'UAH' => '₴', 'NGN' => '₦', 'PHP' => '₱', 'THB' => '฿', 'VND' => '₫'];
}

/**
 * @param bool $mixed True when the view shows more than one currency.
 */
function birkly_money(int $minor, string $currency, bool $mixed = false): string
{
    $currency = strtoupper(trim($currency)) ?: 'EUR';
    $digits = birkly_money_minor_digits($currency);
    $negative = $minor < 0;
    $abs = abs($minor);
    $divisor = 10 ** $digits;
    $whole = intdiv($abs, $divisor);
    $fraction = $digits > 0 ? '.' . str_pad((string) ($abs % $divisor), $digits, '0', STR_PAD_LEFT) : '';
    $amount = number_format($whole, 0, '.', ',') . $fraction;

    $symbols = birkly_money_unambiguous_symbols();
    $text = (!$mixed && isset($symbols[$currency]))
        ? $symbols[$currency] . $amount
        : $currency . "\u{00A0}" . $amount;

    return ($negative ? '−' : '') . $text;
}
