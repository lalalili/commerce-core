<?php

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Services\OrderInvoiceTaxGroupService;

it('parses order numbers with tax suffixes', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);

    expect($service->parseOrderNoWithTax('OD10001-1'))->toBe([
        'order_number' => 'OD10001',
        'tax_type' => 1,
    ])
        ->and($service->parseOrderNoWithTax('INVALID'))->toBeNull()
        ->and($service->parseOrderNoWithTax('OD10001-A'))->toBeNull();
});

it('normalizes order details by tax group', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);

    expect($service->normalizeOrderDetailsWithTax([
        '1' => [
            ['title' => 'Taxed item', 'sales_price' => '200', 'qty' => '2'],
            ['product_title' => '折扣金額', 'sales_price' => -50, 'qty' => 1],
        ],
        0 => [
            ['product_title' => 'Tax free item', 'sales_price' => '100.5', 'qty' => 3],
        ],
    ]))->toBe([
        0 => [
            ['title' => 'Tax free item', 'sales_price' => 100, 'qty' => 3],
        ],
        1 => [
            ['title' => 'Taxed item', 'sales_price' => 200, 'qty' => 2],
            ['title' => '折扣金額', 'sales_price' => -50, 'qty' => 1],
        ],
    ]);
});

it('groups order details by tax bucket and splits discounts by taxable amount', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);

    $groups = $service->groupDetailsByTaxBucket(
        [
            [
                'detail' => ['product_title' => 'Tax free item', 'sales_price' => 100, 'qty' => 1],
                'tax_type' => 0,
                'taxable_amount' => 0,
            ],
            [
                'detail' => ['product_title' => 'Taxed item', 'sales_price' => 80, 'qty' => 2],
                'tax_type' => 1,
                'taxable_amount' => 160,
            ],
        ],
        200,
        ['product_title' => '折扣金額', 'qty' => 1],
        [
            'shipping_line' => ['product_title' => '運費金額', 'sales_price' => 40, 'qty' => 1],
            'shipping_amount' => 40,
        ],
    );

    expect($groups)->toBe([
        0 => [
            ['product_title' => 'Tax free item', 'sales_price' => 100, 'qty' => 1],
        ],
        1 => [
            ['product_title' => 'Taxed item', 'sales_price' => 80, 'qty' => 2],
            ['product_title' => '運費金額', 'sales_price' => 40, 'qty' => 1],
            ['product_title' => '折扣金額', 'qty' => 1, 'sales_price' => -200],
        ],
    ]);
});

it('groups all order details into a forced tax bucket', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);

    $groups = $service->groupDetailsByTaxBucket(
        [
            [
                'detail' => ['product_title' => 'Export item', 'sales_price' => 100, 'qty' => 1],
                'tax_type' => 1,
            ],
        ],
        20,
        ['product_title' => '折扣金額', 'qty' => 1],
        [
            'force_tax_type' => 2,
            'shipping_line' => ['product_title' => '運費金額', 'sales_price' => 50, 'qty' => 1],
            'shipping_amount' => 50,
        ],
    );

    expect($groups)->toBe([
        2 => [
            ['product_title' => 'Export item', 'sales_price' => 100, 'qty' => 1],
            ['product_title' => '運費金額', 'sales_price' => 50, 'qty' => 1],
            ['product_title' => '折扣金額', 'qty' => 1, 'sales_price' => -20],
        ],
    ]);
});

it('issues each normalized tax group through a host issuer callback', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);
    $order = Order::query()->create(['number' => 'OD10002', 'user_id' => 1]);
    $issued = [];

    $responses = $service->issueTaxGroups(
        $order,
        [
            1 => [
                ['title' => 'Taxed item', 'sales_price' => 200, 'qty' => 1],
            ],
            0 => [
                ['product_title' => 'Tax free item', 'sales_price' => 100, 'qty' => 2],
            ],
        ],
        function (string $orderNoWithTax, int $taxType, Model $order, array $details) use (&$issued): array {
            $issued[] = compact('orderNoWithTax', 'taxType', 'details');

            return [
                'order_number' => $orderNoWithTax,
                'tax_type' => $taxType,
                'base_order' => $order->getAttribute('number'),
            ];
        }
    );

    expect($responses)->toBe([
        ['order_number' => 'OD10002-0', 'tax_type' => 0, 'base_order' => 'OD10002'],
        ['order_number' => 'OD10002-1', 'tax_type' => 1, 'base_order' => 'OD10002'],
    ])
        ->and($issued[0]['details'][0])->toBe([
            'title' => 'Tax free item',
            'sales_price' => 100,
            'qty' => 2,
        ]);
});

it('finds selected tax group details across integer and string keys', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);

    $details = [
        0 => [
            ['product_title' => 'Tax free item', 'sales_price' => 100, 'qty' => 1],
        ],
        '1' => [
            ['product_title' => 'Taxed item', 'sales_price' => 200, 'qty' => 1],
        ],
    ];

    expect($service->selectedTaxGroupDetails($details, '0'))->toBe([
        ['product_title' => 'Tax free item', 'sales_price' => 100, 'qty' => 1],
    ])
        ->and($service->selectedTaxGroupDetails($details, 1))->toBe([
            ['product_title' => 'Taxed item', 'sales_price' => 200, 'qty' => 1],
        ])
        ->and($service->selectedTaxGroupDetails($details, 2))->toBeNull();
});

it('issues only the selected tax group', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);
    $order = Order::query()->create(['number' => 'OD10003', 'user_id' => 1]);

    $response = $service->issueSelectedTaxGroup(
        'OD10003-1',
        $order,
        [
            1 => [
                ['title' => 'Taxed item', 'sales_price' => 300, 'qty' => 1],
            ],
        ],
        fn (string $orderNoWithTax, int $taxType, Model $order, array $details): array => [
            'order_number' => $orderNoWithTax,
            'tax_type' => $taxType,
            'details' => $details,
        ],
    );

    expect($response)->toBe([
        'order_number' => 'OD10003-1',
        'tax_type' => 1,
        'details' => [
            ['title' => 'Taxed item', 'sales_price' => 300, 'qty' => 1],
        ],
    ])
        ->and($service->issueSelectedTaxGroup('INVALID', $order, [], fn (): array => ['issued' => true]))->toBe([]);
});
