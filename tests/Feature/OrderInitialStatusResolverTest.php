<?php

use Lalalili\CommerceCore\Services\OrderInitialStatusResolver;

it('resolves pending status for payment types that require payment confirmation', function (): void {
    $status = app(OrderInitialStatusResolver::class)->resolve(
        paymentType: 'credit_card',
        pendingStatus: 'pending_payment',
        readyStatus: 'ready_to_fulfill',
        pendingPaymentTypes: ['credit_card', 'union_pay'],
    );

    expect($status)->toBe('pending_payment');
});

it('resolves ready status for payment types that do not require payment confirmation', function (): void {
    $status = app(OrderInitialStatusResolver::class)->resolve(
        paymentType: 'cash_on_delivery',
        pendingStatus: 'pending_payment',
        readyStatus: 'ready_to_fulfill',
        pendingPaymentTypes: ['credit_card', 'union_pay'],
    );

    expect($status)->toBe('ready_to_fulfill');
});

it('can resolve pending payment types from commerce config', function (): void {
    config()->set('commerce.checkout.pending_payment_types', ['esun', 'ecpay_unionpay']);

    $status = app(OrderInitialStatusResolver::class)->resolve(
        paymentType: 'esun',
        pendingStatus: 0,
        readyStatus: 1,
    );

    expect($status)->toBe(0);
});
