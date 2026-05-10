<?php

namespace Lalalili\CommerceCore\Support;

class OrderNumberGenerator
{
    public function generate(): string
    {
        /** @var class-string $orderModel */
        $orderModel = config('commerce.models.order');
        $alphabet = range('A', 'Z');
        $now = now('Asia/Taipei');

        do {
            $number = $now->format('ymd')
                .$alphabet[$now->hour % 26]
                .$alphabet[$now->minute % 26]
                .$alphabet[$now->second % 26]
                .$alphabet[random_int(0, 25)];
        } while ($orderModel::query()->where('number', $number)->exists());

        return $number;
    }
}
