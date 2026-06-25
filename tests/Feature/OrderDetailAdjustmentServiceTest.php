<?php

use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Services\OrderDetailAdjustmentService;

it('builds a reusable discount adjustment line', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    expect($service->discountAdjustmentLine())->toBe([
        'product_id' => 'POS-1',
        'product_title' => '折扣金額',
        'qty' => 1,
        'product' => ['erp_size' => 999],
    ])->and($service->discountAdjustmentLine('product_number'))->toBe([
        'product_number' => 'POS-1',
        'product_title' => '折扣金額',
        'qty' => 1,
        'product' => ['erp_size' => 999],
    ]);
});

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

it('appends shipping and discount accounting lines', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    $details = $service->appendAccountingLines([
        [
            'product_number' => 'BOOK-1',
            'sales_price' => 500,
            'qty' => 1,
        ],
    ], 80, [
        'product_number' => 'POS-1',
        'product_title' => '折扣金額',
        'qty' => 1,
    ], [
        'product_number' => '70000001',
        'product_title' => '運費金額',
        'qty' => 1,
    ], 120);

    expect($details)->toBe([
        [
            'product_number' => 'BOOK-1',
            'sales_price' => 500,
            'qty' => 1,
        ],
        [
            'product_number' => '70000001',
            'product_title' => '運費金額',
            'qty' => 1,
            'sales_price' => 120,
        ],
        [
            'product_number' => 'POS-1',
            'product_title' => '折扣金額',
            'qty' => 1,
            'sales_price' => -80,
        ],
    ]);
});

it('does not append empty shipping accounting lines', function (): void {
    $service = app(OrderDetailAdjustmentService::class);

    expect($service->appendAccountingLines([], 0, [
        'product_number' => 'POS-1',
    ], [
        'product_number' => '70000001',
    ], 0))->toBe([]);
});

it('builds accounting details directly from an order', function (): void {
    $service = app(OrderDetailAdjustmentService::class);
    $order = Order::query()->create([
        'number' => 'ODADJ001',
        'user_id' => 1,
        'total_discount_amt' => 80,
    ]);
    $product = Product::query()->create([
        'title' => 'Accounting item',
        'type' => 1,
        'list_price' => 500,
        'sales_price' => 500,
        'tax' => 1,
    ]);

    OrderDetail::query()->create([
        'order_id' => $order->id,
        'order_number' => $order->number,
        'product_id' => $product->id,
        'title' => 'Accounting item',
        'qty' => 1,
        'list_price' => 500,
        'sales_price' => 500,
        'status' => 0,
    ]);

    $details = $service->orderAccountingDetails(
        $order,
        [
            'product_number' => 'POS-1',
            'product_title' => '折扣金額',
            'qty' => 1,
        ],
        [
            'product_number' => '70000001',
            'product_title' => '運費金額',
            'qty' => 1,
        ],
        120,
    );

    expect($details)->toHaveCount(3)
        ->and($details[0]['title'])->toBe('Accounting item')
        ->and($details[1])->toMatchArray([
            'product_number' => '70000001',
            'product_title' => '運費金額',
            'qty' => 1,
            'sales_price' => 120,
        ])
        ->and($details[2])->toMatchArray([
            'product_number' => 'POS-1',
            'product_title' => '折扣金額',
            'qty' => 1,
            'sales_price' => -80,
        ]);
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
