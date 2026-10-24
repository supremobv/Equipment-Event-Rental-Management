<?php

namespace App\Support;

class Money
{
    public static function ruleWithoutRequired(): array
    {
        return ['numeric', 'min:0', 'max:99999999', 'regex:/^\d+(\.\d{1,2})?$/'];
    }

    public static function cents(string|int|float|null $value): int
    {
        $parts = explode('.', (string) ($value ?? '0'));

        return (int) $parts[0] * 100 + (int) str_pad(substr($parts[1] ?? '', 0, 2), 2, '0');
    }

    public static function format(int|float $cents): string
    {
        return number_format($cents / 100, 2);
    }

    public static function rule(): array
    {
        return ['required', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d+(\.\d{1,2})?$/'];
    }
}
