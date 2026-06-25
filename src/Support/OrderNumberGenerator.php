<?php

namespace Lalalili\CommerceCore\Support;

use InvalidArgumentException;

class OrderNumberGenerator
{
    public function generate(): string
    {
        /** @var class-string $orderModel */
        $orderModel = config('commerce.models.order');

        do {
            $number = $this->generateTimestampLetterCode();
        } while ($orderModel::query()->where('number', $number)->exists());

        return $number;
    }

    public function generateTimestampLetterCode(string $timezone = 'Asia/Taipei'): string
    {
        $alphabet = range('A', 'Z');
        $now = now($timezone);

        return $now->format('ymd')
            .$alphabet[$now->hour % 26]
            .$alphabet[$now->minute % 26]
            .$alphabet[$now->second % 26]
            .$alphabet[random_int(0, 25)];
    }

    public function generateTimestampNumericCode(
        int $id,
        string $timezone = 'Asia/Taipei',
        int $idDigits = 6,
        int $randomDigits = 2,
    ): string {
        if ($idDigits < 1 || $randomDigits < 1) {
            throw new InvalidArgumentException('ID and random digit widths must be greater than zero.');
        }

        $idModulo = 10 ** $idDigits;
        $randomMaximum = (10 ** $randomDigits) - 1;

        return now($timezone)->format('ymdHis')
            .str_pad((string) ($id % $idModulo), $idDigits, '0', STR_PAD_LEFT)
            .str_pad((string) random_int(0, $randomMaximum), $randomDigits, '0', STR_PAD_LEFT);
    }
}
