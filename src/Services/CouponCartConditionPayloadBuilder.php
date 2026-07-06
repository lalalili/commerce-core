<?php

namespace Lalalili\CommerceCore\Services;

use InvalidArgumentException;

class CouponCartConditionPayloadBuilder
{
    /**
     * @deprecated order 改由 config `discount.ordering.coupon.member` 決定(single source),
     *             常數僅作 config 未設定時的 fallback。
     */
    public const MEMBER_COUPON_CONDITION_ORDER = 10;

    /**
     * @deprecated order 改由 config `discount.ordering.coupon.promotion` 決定(single source),
     *             常數僅作 config 未設定時的 fallback。
     */
    public const PROMOTION_COUPON_CONDITION_ORDER = 11;

    /**
     * fallback;實際值由 config `discount.ordering.coupon.free_shipping` 決定。
     */
    public const FREE_SHIPPING_COUPON_CONDITION_ORDER = 2;

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
            'target' => $this->targetFor($kind),
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
            'free_shipping' => 'shipping_coupon',
            default => throw new InvalidArgumentException("Unsupported coupon kind [{$kind}]."),
        };
    }

    /**
     * 免運券掛 subtotal 層(緊跟 host shipping_fee condition 之後),其餘掛 total 層。
     */
    public function targetFor(string $kind): string
    {
        return $kind === 'free_shipping' ? 'subtotal' : 'total';
    }

    public function orderFor(string $kind): int
    {
        return match ($kind) {
            'member' => (int) config('discount.ordering.coupon.member', self::MEMBER_COUPON_CONDITION_ORDER),
            'promotion' => (int) config('discount.ordering.coupon.promotion', self::PROMOTION_COUPON_CONDITION_ORDER),
            'free_shipping' => (int) config('discount.ordering.coupon.free_shipping', self::FREE_SHIPPING_COUPON_CONDITION_ORDER),
            default => throw new InvalidArgumentException("Unsupported coupon kind [{$kind}]."),
        };
    }
}
