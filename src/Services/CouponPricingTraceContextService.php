<?php

namespace Lalalili\CommerceCore\Services;

class CouponPricingTraceContextService
{
    public function __construct(
        private readonly CouponTracePayloadResolver $couponPayloads,
        private readonly CouponPricingTraceEntryFactory $entryPayloads,
        private readonly CouponPricingTraceService $traces,
        private readonly CouponCartPricingTraceService $cartTraces,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function validationEntry(
        string $kind,
        string $code,
        mixed $coupon,
        string $status,
        int|float|string|null $amount = null,
        ?float $finalTotal = null,
        ?string $reasonCode = null,
        ?string $reason = null,
    ): array {
        $payload = $this->couponPayloads->payload($coupon);

        return $this->entryPayloads->validation(
            kind: $kind,
            code: $code,
            couponCode: $payload['code'],
            couponId: $payload['id'],
            scope: $payload['scope'],
            status: $status,
            amount: $amount,
            finalTotal: $finalTotal,
            reasonCode: $reasonCode,
            reason: $reason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function applyEntry(
        string $kind,
        string $code,
        mixed $coupon,
        float $discount,
        float $finalTotal,
        ?string $reasonCode = null,
        ?string $reason = null,
    ): array {
        $payload = $this->couponPayloads->payload($coupon);

        return $this->entryPayloads->apply(
            kind: $kind,
            code: $code,
            couponCode: $payload['code'],
            couponId: $payload['id'],
            scope: $payload['scope'],
            discount: $discount,
            finalTotal: $finalTotal,
            reasonCode: $reasonCode,
            reason: $reason,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function lifecycleEntry(
        string $stage,
        string $status,
        string $kind,
        mixed $coupon,
        ?string $reasonCode = null,
        ?string $reason = null,
    ): array {
        $payload = $this->couponPayloads->payload($coupon);

        return $this->entryPayloads->lifecycle(
            stage: $stage,
            status: $status,
            kind: $kind,
            couponCode: $payload['code'] ?? '',
            couponId: $payload['id'],
            scope: $payload['scope'],
            limitQuantity: $payload['limit_qty'],
            leftQuantity: $payload['left_qty'],
            reasonCode: $reasonCode,
            reason: $reason,
        );
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $trace
     */
    public function appendCouponTrace(mixed $cart, ?array $trace): void
    {
        $entries = $this->normalizeEntries($trace);
        if ($entries === []) {
            return;
        }

        $this->cartTraces->appendCouponTrace($cart, $entries);
    }

    public function clearPricingTrace(mixed $cart): void
    {
        $this->cartTraces->clearPricingTrace($cart);
    }

    public function clearCouponTrace(mixed $cart, ?string $kind = null, ?string $code = null): void
    {
        $this->cartTraces->clearCouponTrace($cart, $kind, $code);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $trace
     * @return list<array<string, mixed>>
     */
    public function normalizeEntries(?array $trace): array
    {
        return $this->traces->normalizeEntries($trace);
    }

    /**
     * @param  array<string, mixed>|list<array<string, mixed>>|null  $trace
     * @return array<string, mixed>
     */
    public function firstEntryArray(?array $trace): array
    {
        return $this->normalizeEntries($trace)[0] ?? [];
    }
}
