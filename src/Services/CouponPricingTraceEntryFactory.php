<?php

namespace Lalalili\CommerceCore\Services;

class CouponPricingTraceEntryFactory
{
    /**
     * @return array{
     *     stage: string,
     *     source: string,
     *     status: string,
     *     scope: string,
     *     kind: string,
     *     code: string|null,
     *     id: int|string|null,
     *     amount: int|float|string|null,
     *     finalTotal: float|null,
     *     reasonCode: string|null,
     *     reason: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function validation(
        string $kind,
        string $code,
        ?string $couponCode,
        int|string|null $couponId,
        string $scope,
        string $status,
        int|float|string|null $amount = null,
        ?float $finalTotal = null,
        ?string $reasonCode = null,
        ?string $reason = null,
    ): array {
        return $this->make(
            stage: 'coupon_validate',
            kind: $kind,
            code: $couponCode ?? $code,
            couponId: $couponId,
            scope: $scope,
            status: $status,
            amount: $amount,
            finalTotal: $finalTotal,
            reasonCode: $reasonCode,
            reason: $reason,
        );
    }

    /**
     * @return array{
     *     stage: string,
     *     source: string,
     *     status: string,
     *     scope: string,
     *     kind: string,
     *     code: string|null,
     *     id: int|string|null,
     *     amount: int|float|string|null,
     *     finalTotal: float|null,
     *     reasonCode: string|null,
     *     reason: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function apply(
        string $kind,
        string $code,
        ?string $couponCode,
        int|string|null $couponId,
        string $scope,
        float $discount,
        float $finalTotal,
        ?string $reasonCode = null,
        ?string $reason = null,
    ): array {
        return $this->make(
            stage: 'coupon_apply',
            kind: $kind,
            code: $couponCode ?? $code,
            couponId: $couponId,
            scope: $scope,
            status: $discount > 0 ? 'applied' : 'skipped',
            amount: $discount,
            finalTotal: $finalTotal,
            reasonCode: $reasonCode,
            reason: $reason,
        );
    }

    /**
     * @return array{
     *     stage: string,
     *     source: string,
     *     status: string,
     *     scope: string,
     *     kind: string,
     *     code: string|null,
     *     id: int|string|null,
     *     amount: int|float|string|null,
     *     finalTotal: float|null,
     *     reasonCode: string|null,
     *     reason: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    public function lifecycle(
        string $stage,
        string $status,
        string $kind,
        string $couponCode,
        int|string|null $couponId,
        string $scope,
        mixed $limitQuantity = null,
        mixed $leftQuantity = null,
        ?string $reasonCode = null,
        ?string $reason = null,
    ): array {
        return $this->make(
            stage: $stage,
            kind: $kind,
            code: $couponCode,
            couponId: $couponId,
            scope: $scope,
            status: $status,
            reasonCode: $reasonCode,
            reason: $reason,
            metadata: [
                'limit_qty' => $limitQuantity,
                'left_qty' => $leftQuantity,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{
     *     stage: string,
     *     source: string,
     *     status: string,
     *     scope: string,
     *     kind: string,
     *     code: string|null,
     *     id: int|string|null,
     *     amount: int|float|string|null,
     *     finalTotal: float|null,
     *     reasonCode: string|null,
     *     reason: string|null,
     *     metadata: array<string, mixed>
     * }
     */
    private function make(
        string $stage,
        string $kind,
        ?string $code,
        int|string|null $couponId,
        string $scope,
        string $status,
        int|float|string|null $amount = null,
        ?float $finalTotal = null,
        ?string $reasonCode = null,
        ?string $reason = null,
        array $metadata = [],
    ): array {
        return [
            'stage' => $stage,
            'source' => 'coupon',
            'status' => $status,
            'scope' => $scope,
            'kind' => $kind,
            'code' => $code,
            'id' => $couponId,
            'amount' => $amount,
            'finalTotal' => $finalTotal,
            'reasonCode' => $reasonCode,
            'reason' => $reason,
            'metadata' => array_replace(['coupon_kind' => $kind], $metadata),
        ];
    }
}
