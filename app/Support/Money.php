<?php

namespace App\Support;

class Money
{
    public const MAX = 9999999999999999;

    public static function cents(string $amount): int
    {
        if (! preg_match('/^\d{1,14}(?:\.\d{1,2})?$/', $amount)) {
            throw new \InvalidArgumentException('Invalid money amount.');
        }
        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function format(int $amount): string
    {
        return intdiv($amount, 100).'.'.str_pad((string) ($amount % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function multiply(int $amount, int $quantity): int
    {
        abort_if($quantity < 0 || ($quantity > 0 && $amount > intdiv(self::MAX, $quantity)), 422, 'Jami narx ruxsat etilgan chegaradan oshdi.');

        return $amount * $quantity;
    }
}
