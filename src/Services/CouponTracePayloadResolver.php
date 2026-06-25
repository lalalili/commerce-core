<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Throwable;

class CouponTracePayloadResolver
{
    /**
     * @return array{
     *     code: string|null,
     *     id: int|string|null,
     *     scope: string,
     *     limit_qty: mixed,
     *     left_qty: mixed
     * }
     */
    public function payload(mixed $coupon): array
    {
        return [
            'code' => $this->code($coupon),
            'id' => $this->id($coupon),
            'scope' => $this->scope($coupon),
            'limit_qty' => data_get($coupon, 'limit_qty'),
            'left_qty' => data_get($coupon, 'left_qty'),
        ];
    }

    public function code(mixed $coupon): ?string
    {
        $code = data_get($coupon, 'code');

        return is_int($code) || is_string($code) ? (string) $code : null;
    }

    public function id(mixed $coupon): int|string|null
    {
        $key = $this->modelKey($coupon);

        if (is_int($key) || is_string($key)) {
            return $key;
        }

        $id = data_get($coupon, 'id');

        return is_int($id) || is_string($id) ? $id : null;
    }

    public function scope(mixed $coupon): string
    {
        $scope = data_get($coupon, 'scope');

        if ($scope instanceof BackedEnum) {
            return (string) $scope->value;
        }

        return is_int($scope) || is_float($scope) || is_string($scope) ? (string) $scope : '';
    }

    private function modelKey(mixed $coupon): mixed
    {
        if (! is_object($coupon) || ! method_exists($coupon, 'getKey')) {
            return null;
        }

        try {
            return $coupon->getKey();
        } catch (Throwable) {
            return null;
        }
    }
}
