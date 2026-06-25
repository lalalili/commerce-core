<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;

class CouponDataPayloadResolver
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     code: string,
     *     scope: int|string,
     *     trigger_amount: int|float|null,
     *     amount: float,
     *     amount_mode: string|null,
     *     status: bool,
     *     limit_qty: int|null,
     *     left_qty: int|null,
     *     user_id: int|null,
     *     attributes: array<string, mixed>
     * }
     */
    public function payload(
        mixed $coupon,
        int|string|null $scope = null,
        ?bool $status = null,
        array $attributes = [],
    ): array {
        return [
            'code' => $this->stringValue(data_get($coupon, 'code')) ?? '',
            'scope' => $scope ?? $this->scope($coupon),
            'trigger_amount' => $this->numericValue(data_get($coupon, 'trigger_amount')),
            'amount' => (float) (data_get($coupon, 'amount') ?? 0),
            'amount_mode' => $this->stringValue(data_get($coupon, 'amount_mode')),
            'status' => $status ?? $this->status($coupon),
            'limit_qty' => $this->integerValue(data_get($coupon, 'limit_qty')),
            'left_qty' => $this->integerValue(data_get($coupon, 'left_qty')),
            'user_id' => $this->integerValue(data_get($coupon, 'user_id')),
            'attributes' => $attributes,
        ];
    }

    private function scope(mixed $coupon): int|string
    {
        $scope = data_get($coupon, 'scope');

        if ($scope instanceof BackedEnum) {
            return $scope->value;
        }

        if (is_int($scope) || is_string($scope)) {
            return $scope;
        }

        if (is_float($scope)) {
            return (string) $scope;
        }

        return '';
    }

    private function status(mixed $coupon): bool
    {
        $status = data_get($coupon, 'status');

        if ($status instanceof BackedEnum) {
            return (bool) $status->value;
        }

        return (bool) $status;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return is_int($value) || is_float($value) || is_string($value)
            ? (string) $value
            : null;
    }

    private function integerValue(mixed $value): ?int
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    private function numericValue(mixed $value): int|float|null
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return is_float($value) ? $value : $value + 0;
    }
}
