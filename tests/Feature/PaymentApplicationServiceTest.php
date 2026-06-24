<?php

use Lalalili\CommerceCore\DTOs\PaymentApplicationData;
use Lalalili\CommerceCore\Enums\OrderStatus;
use Lalalili\CommerceCore\Enums\PaymentApplicationOutcome;
use Lalalili\CommerceCore\Enums\PaymentStatus;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Models\ProductUser;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommerceCore\Services\PaymentApplicationService;
use Lalalili\CommerceCore\Tests\Support\RecordingPaymentApplicationHook;

function createPaymentApplicationOrder(string $number = '260524APPL', int $amount = 1000): Order
{
    $product = Product::query()->create([
        'title' => 'Payment application course',
        'type' => 1,
        'list_price' => $amount,
        'sales_price' => $amount,
        'tax' => 1,
    ]);

    /** @var Order $order */
    $order = app(OrderLifecycleService::class)->create(1, [
        ['product_id' => $product->id],
    ], [
        'number' => $number,
    ]);

    return $order;
}

it('applies paid payment results when the amount matches', function (): void {
    $order = createPaymentApplicationOrder('260524PAID', 1000);

    $applied = app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: $order->number,
        outcome: PaymentApplicationOutcome::Paid,
        payload: ['TradeStatus' => '1'],
        statusCode: '1',
        statusMessage: '訂單成立已付款',
        amount: 1000,
        paidAt: now(),
        gatewayLabel: '綠界',
    ));

    expect($applied?->payment_status)->toBe(PaymentStatus::Complete)
        ->and($applied?->status)->toBe(OrderStatus::Complete)
        ->and(PaymentLog::query()->where('order_number', $order->number)->where('status_code', '1')->exists())->toBeTrue()
        ->and(ProductUser::query()->where('user_id', 1)->count())->toBe(1);
});

it('does not mark paid when the paid amount mismatches the order total', function (): void {
    $order = createPaymentApplicationOrder('260524MISS', 1000);

    $applied = app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: $order->number,
        outcome: PaymentApplicationOutcome::Paid,
        payload: ['TradeStatus' => '1'],
        statusCode: '1',
        statusMessage: '訂單成立已付款',
        amount: 999,
        paidAt: now(),
        gatewayLabel: '綠界',
    ));

    expect($applied?->payment_status)->toBe(PaymentStatus::Pending)
        ->and($applied?->status)->toBe(OrderStatus::Pending)
        ->and(PaymentLog::query()->where('order_number', $order->number)->first()?->status_message)
        ->toBe('綠界付款金額999與訂單金額不符');
});

it('does not mark paid without a paid timestamp', function (): void {
    $order = createPaymentApplicationOrder('260524TIME', 1000);

    $applied = app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: $order->number,
        outcome: PaymentApplicationOutcome::Paid,
        payload: ['TradeStatus' => '1'],
        statusCode: '1',
        statusMessage: '訂單成立已付款',
        amount: 1000,
    ));

    expect($applied?->payment_status)->toBe(PaymentStatus::Pending)
        ->and($applied?->payment_status_message)->toBe('訂單成立已付款');
});

it('updates payment messages for non-terminal host-policy outcomes', function (PaymentApplicationOutcome $outcome): void {
    $order = createPaymentApplicationOrder('260524MSG'.$outcome->name[0], 1000);

    $applied = app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: $order->number,
        outcome: $outcome,
        payload: ['status' => $outcome->value],
        statusCode: $outcome->value,
        statusMessage: 'gateway message',
        outcomeMessage: 'host message',
    ));

    expect($applied?->payment_status)->toBe(PaymentStatus::Pending)
        ->and($applied?->payment_status_message)->toBe('host message');
})->with([
    PaymentApplicationOutcome::Pending,
    PaymentApplicationOutcome::Declined,
    PaymentApplicationOutcome::UserCancelled,
    PaymentApplicationOutcome::QueryFailed,
]);

it('marks refunded payments through the order lifecycle', function (): void {
    $order = createPaymentApplicationOrder('260524RFND', 1000);
    app(OrderLifecycleService::class)->markPaid($order->number, 'paid', now());

    $applied = app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: $order->number,
        outcome: PaymentApplicationOutcome::Refunded,
        payload: ['status' => 'refunded'],
        statusCode: '69',
        statusMessage: '退貨成功',
        outcomeMessage: '銀行已退款',
    ));

    expect($applied?->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($applied?->payment_status_message)->toBe('銀行已退款')
        ->and(ProductUser::query()->where('user_id', 1)->exists())->toBeTrue();
});

it('records query failures even when the order does not exist', function (): void {
    $applied = app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: '260524NONE',
        outcome: PaymentApplicationOutcome::QueryFailed,
        payload: ['message' => '查詢失敗'],
        statusCode: '',
        statusMessage: '付款狀態查詢失敗',
    ));

    expect($applied)->toBeNull()
        ->and(PaymentLog::query()->where('order_number', '260524NONE')->where('status_message', '付款狀態查詢失敗')->exists())
        ->toBeTrue();
});

it('dispatches payment application hooks after applying a payment result', function (): void {
    RecordingPaymentApplicationHook::reset();
    config()->set('commerce.payment.hooks', [RecordingPaymentApplicationHook::class]);
    $order = createPaymentApplicationOrder('260524HOOK', 1000);

    app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: $order->number,
        outcome: PaymentApplicationOutcome::Paid,
        payload: ['TradeStatus' => '1'],
        statusCode: '1',
        statusMessage: '訂單成立已付款',
        amount: 1000,
        paidAt: now(),
        gatewayLabel: '綠界',
    ));

    expect(RecordingPaymentApplicationHook::$events)->toBe([
        [
            'outcome' => 'paid',
            'order_number' => '260524HOOK',
            'order_exists' => true,
        ],
    ]);
});

it('dispatches payment application hooks when the order does not exist', function (): void {
    RecordingPaymentApplicationHook::reset();
    config()->set('commerce.payment.hooks', [RecordingPaymentApplicationHook::class]);

    app(PaymentApplicationService::class)->apply(new PaymentApplicationData(
        orderNumber: '260524NOHK',
        outcome: PaymentApplicationOutcome::QueryFailed,
        payload: ['message' => '查詢失敗'],
        statusCode: '',
        statusMessage: '付款狀態查詢失敗',
    ));

    expect(RecordingPaymentApplicationHook::$events)->toBe([
        [
            'outcome' => 'query_failed',
            'order_number' => '260524NOHK',
            'order_exists' => false,
        ],
    ]);
});
