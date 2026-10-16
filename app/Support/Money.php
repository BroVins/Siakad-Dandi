<?php

namespace App\Support;

/** Decimal amounts are converted to integer hundredths; never sum money as floats. */
final class Money
{
    public const MAX_CENTS = 9999999999999;

    public static function cents(string|int|float $amount): int
    {
        $value = (string) $amount;
        if (! preg_match('/^(0|[1-9][0-9]{0,10})(?:\.([0-9]{1,2}))?$/D', $value, $parts)) {
            throw new \InvalidArgumentException('Nominal harus berupa angka desimal biasa, maksimal dua angka di belakang titik.');
        }
        $cents = ((int) $parts[1] * 100) + (int) str_pad($parts[2] ?? '', 2, '0');
        if ($cents > self::MAX_CENTS) {
            throw new \InvalidArgumentException('Nominal melebihi batas penyimpanan.');
        }
        return $cents;
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function display(int $cents): string
    {
        return number_format(intdiv($cents, 100), 0, ',', '.').','.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
