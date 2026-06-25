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

it('reads scalar response values from successful payment logs', function (): void {
    $service = app(PaymentLogService::class);

    $service->record(
        orderNumber: '260510CARD',
        payload: ['DATA' => ['txnData' => ['AN' => '1234-5678']]],
        statusCode: '0',
        statusMessage: 'Paid',
    );

    $service->record(
        orderNumber: '260510CARD',
        payload: ['DATA' => ['txnData' => ['AN' => '9999']]],
        statusCode: '30',
        statusMessage: 'Failed',
    );

    expect($service->responseValue('260510CARD', 'DATA.txnData.AN', [0, 11]))
        ->toBe('1234-5678')
        ->and($service->responseValue('260510CARD', 'DATA.txnData.MISSING', [0, 11]))
        ->toBe('');
});
