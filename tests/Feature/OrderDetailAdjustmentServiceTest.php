<?php

use Lalalili\CommerceCore\Services\OrderDetailAdjustmentService;

it('appends a negative discount line when the order has a discount', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    $details = $service->appendDiscountLine([
        [
            'product_id' => 10,
            'sales_price' => 1000,
            'qty' => 1,
        ],
    ], 150, [
        'product_id' => 'POS-1',
        'product_title' => '折扣金額',
        'qty' => 1,
        'product' => ['erp_size' => 999],
    ]);

    expect($details)->toBe([
        [
            'product_id' => 10,
            'sales_price' => 1000,
            'qty' => 1,
        ],
        [
            'product_id' => 'POS-1',
            'product_title' => '折扣金額',
            'qty' => 1,
            'product' => ['erp_size' => 999],
            'sales_price' => -150,
        ],
    ]);
});

it('does not append a discount line when the discount is empty', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    expect($service->appendDiscountLine([], 0, [
        'product_id' => 'POS-1',
    ]))->toBe([]);
});

it('puts the whole discount in the taxable bucket when taxable total covers it', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    $lines = $service->discountLinesByTaxBucket(80, 200, [
        'product_number' => 'POS-1',
        'product_title' => '折扣金額',
        'qty' => 1,
    ]);

    expect($lines)->toBe([
        1 => [
            [
                'product_number' => 'POS-1',
                'product_title' => '折扣金額',
                'qty' => 1,
                'sales_price' => -80,
            ],
        ],
    ]);
});

it('splits discounts between taxable and tax free buckets', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    $lines = $service->discountLinesByTaxBucket(150, 80, [
        'product_id' => 'POS-1',
        'product_title' => '折扣金額',
        'qty' => 1,
        'product' => ['erp_size' => 999],
    ]);

    expect($lines)->toBe([
        1 => [
            [
                'product_id' => 'POS-1',
                'product_title' => '折扣金額',
                'qty' => 1,
                'product' => ['erp_size' => 999],
                'sales_price' => -80,
            ],
        ],
        0 => [
            [
                'product_id' => 'POS-1',
                'product_title' => '折扣金額',
                'qty' => 1,
                'product' => ['erp_size' => 999],
                'sales_price' => -70,
            ],
        ],
    ]);
});
