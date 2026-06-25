<?php

namespace Lalalili\CommerceCore\Services;

use InvalidArgumentException;

class CouponCartConditionPayloadBuilder
{
    public const MEMBER_COUPON_CONDITION_ORDER = 10;

    public const PROMOTION_COUPON_CONDITION_ORDER = 11;

    /**
     * @param  array<string, mixed>  $pricingTraceEntry
     * @return array{
     *     name: string,
     *     type: string,
     *     target: string,
     *     value: int|float,
     *     order: int,
     *     attributes: array{pricing_trace_entry: array<string, mixed>}
     * }
     */
    public function payload(
        string $kind,
        int|float $discount,
        array $pricingTraceEntry,
        string $name,
    ): array {
        return [
            'name' => $name,
            'type' => $this->typeFor($kind),
            'target' => 'total',
            'value' => -1 * $discount,
            'order' => $this->orderFor($kind),
            'attributes' => [
                'pricing_trace_entry' => $pricingTraceEntry,
            ],
        ];
    }

    public function typeFor(string $kind): string
    {
        return match ($kind) {
            'member' => 'member_coupon',
            'promotion' => 'promotion_coupon',
            default => throw new InvalidArgumentException("Unsupported coupon kind [{$kind}]."),
        };
    }

    public function orderFor(string $kind): int
    {
        return match ($kind) {
            'member' => self::MEMBER_COUPON_CONDITION_ORDER,
            'promotion' => self::PROMOTION_COUPON_CONDITION_ORDER,
            default => throw new InvalidArgumentException("Unsupported coupon kind [{$kind}]."),
        };
    }
}
