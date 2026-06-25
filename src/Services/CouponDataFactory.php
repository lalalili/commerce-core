<?php

namespace Lalalili\CommerceCore\Services;

class CouponDataFactory
{
    public function __construct(private readonly CouponDataPayloadResolver $payloads) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @template T of object
     *
     * @param  class-string<T>  $couponDataClass
     * @return T
     */
    public function fromCoupon(
        mixed $coupon,
        mixed $kind,
        string $couponDataClass,
        int|string|null $scope = null,
        ?bool $status = null,
        array $attributes = [],
    ): object {
        return $this->fromPayload(
            payload: $this->payloads->payload(
                coupon: $coupon,
                scope: $scope,
                status: $status,
                attributes: $attributes,
            ),
            kind: $kind,
            couponDataClass: $couponDataClass,
        );
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $couponDataClass
     * @param  array{
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
     * }  $payload
     * @return T
     */
    public function fromPayload(array $payload, mixed $kind, string $couponDataClass): object
    {
        return new $couponDataClass(
            code: $payload['code'],
            kind: $kind,
            scope: (int) $payload['scope'],
            triggerAmount: $payload['trigger_amount'],
            amount: $payload['amount'],
            amountMode: $payload['amount_mode'],
            status: $payload['status'],
            limitQty: $payload['limit_qty'],
            leftQty: $payload['left_qty'],
            userId: $payload['user_id'],
            attributes: $payload['attributes'],
        );
    }
}
