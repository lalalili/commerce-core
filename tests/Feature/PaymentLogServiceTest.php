<?php

use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommerceCore\Services\PaymentLogService;

it('records payment logs by order number and status code', function (): void {
    $service = app(PaymentLogService::class);

    $created = $service->record(
        orderNumber: '260510PAYL',
        payload: ['TradeStatus' => '1'],
        statusCode: '1',
        statusMessage: 'Paid',
    );

    $updated = $service->record(
        orderNumber: '260510PAYL',
        payload: ['TradeStatus' => '1', 'Amount' => 1000],
        statusCode: '1',
        statusMessage: 'Paid again',
    );

    expect($updated->getKey())->toBe($created->getKey())
        ->and(PaymentLog::query()->count())->toBe(1)
        ->and($updated->refresh()->response)->toBe(['TradeStatus' => '1', 'Amount' => 1000])
        ->and($updated->status_message)->toBe('Paid again');
});
