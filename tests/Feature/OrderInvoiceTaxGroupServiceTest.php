<?php

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\Product;
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

it('normalizes tax group keys without changing detail payloads', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);

    expect($service->normalizeTaxGroupKeys([
        '2' => [
            ['product_number' => 'A', 'sales_price' => '100'],
        ],
        '0' => [
            ['product_number' => 'B', 'meta' => ['gift' => true]],
        ],
    ]))->toBe([
        0 => [
            ['product_number' => 'B', 'meta' => ['gift' => true]],
        ],
        2 => [
            ['product_number' => 'A', 'sales_price' => '100'],
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

it('builds tax groups directly from order details', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);
    $taxedProduct = Product::query()->create([
        'title' => 'Taxed item',
        'type' => 1,
        'list_price' => 200,
        'sales_price' => 180,
        'tax' => 1,
    ]);
    $taxFreeProduct = Product::query()->create([
        'title' => 'Tax free item',
        'type' => 1,
        'list_price' => 100,
        'sales_price' => 90,
        'tax' => 0,
    ]);
    $order = Order::query()->create([
        'number' => 'OD10004',
        'user_id' => 1,
        'total_discount_amt' => 30,
    ]);

    OrderDetail::query()->create([
        'order_id' => $order->id,
        'order_number' => $order->number,
        'product_id' => $taxedProduct->id,
        'title' => 'Taxed item',
        'qty' => 1,
        'list_price' => 200,
        'sales_price' => 180,
        'status' => 0,
    ]);
    OrderDetail::query()->create([
        'order_id' => $order->id,
        'order_number' => $order->number,
        'product_id' => $taxFreeProduct->id,
        'title' => 'Tax free item',
        'qty' => 1,
        'list_price' => 100,
        'sales_price' => 90,
        'status' => 0,
    ]);

    $groups = $service->groupOrderDetailsByTaxBucket(
        $order,
        30,
        ['product_title' => '折扣金額', 'qty' => 1],
        ['taxable_amount_key' => 'list_price'],
        fn (Model $detail): array => [
            'title' => (string) $detail->getAttribute('title'),
            'sales_price' => (int) $detail->getAttribute('sales_price'),
            'qty' => (int) $detail->getAttribute('qty'),
        ],
    );

    expect($groups)->toBe([
        0 => [
            ['title' => 'Tax free item', 'sales_price' => 90, 'qty' => 1],
        ],
        1 => [
            ['title' => 'Taxed item', 'sales_price' => 180, 'qty' => 1],
            ['product_title' => '折扣金額', 'qty' => 1, 'sales_price' => -30],
        ],
    ]);
});

it('can force order detail tax groups and append shipping from options', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);
    $product = Product::query()->create([
        'title' => 'Export item',
        'type' => 1,
        'list_price' => 100,
        'sales_price' => 100,
        'tax' => 1,
    ]);
    $order = Order::query()->create([
        'number' => 'OD10005',
        'user_id' => 1,
        'total_discount_amt' => 20,
    ]);

    OrderDetail::query()->create([
        'order_id' => $order->id,
        'order_number' => $order->number,
        'product_id' => $product->id,
        'title' => 'Export item',
        'qty' => 1,
        'list_price' => 100,
        'sales_price' => 100,
        'status' => 0,
    ]);

    $groups = $service->groupOrderDetailsByTaxBucket(
        $order,
        20,
        ['product_title' => '折扣金額', 'qty' => 1],
        [
            'force_tax_type' => 2,
            'shipping_line' => ['product_title' => '運費金額', 'sales_price' => 50, 'qty' => 1],
            'shipping_amount' => 50,
        ],
        fn (Model $detail): array => [
            'product_title' => (string) $detail->getAttribute('title'),
            'sales_price' => (int) $detail->getAttribute('sales_price'),
            'qty' => (int) $detail->getAttribute('qty'),
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

it('resolves and issues the selected tax group from an order number with tax suffix', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);
    $order = Order::query()->create(['number' => 'OD10006', 'user_id' => 1]);

    $response = $service->issueSelectedTaxGroupByOrderNoWithTax(
        orderNoWithTax: 'OD10006-1',
        orderResolver: fn (string $orderNumber): ?Model => $orderNumber === 'OD10006' ? $order : null,
        orderDetailsResolver: fn (Model $order): array => [
            1 => [
                ['title' => 'Taxed item', 'sales_price' => 300, 'qty' => 2],
            ],
            0 => [
                ['title' => 'Tax free item', 'sales_price' => 100, 'qty' => 1],
            ],
        ],
        issuer: fn (string $orderNoWithTax, int $taxType, Model $order, array $details): array => [
            'order_number' => $orderNoWithTax,
            'tax_type' => $taxType,
            'base_order' => $order->getAttribute('number'),
            'details' => $details,
        ],
    );

    expect($response)->toBe([
        'order_number' => 'OD10006-1',
        'tax_type' => 1,
        'base_order' => 'OD10006',
        'details' => [
            ['title' => 'Taxed item', 'sales_price' => 300, 'qty' => 2],
        ],
    ]);
});

it('reports selected tax group resolution failures to host callbacks', function (): void {
    $service = app(OrderInvoiceTaxGroupService::class);
    $order = Order::query()->create(['number' => 'OD10007', 'user_id' => 1]);
    $failures = [];
    $failure = function (string $reason, array $context) use (&$failures): void {
        $failures[] = compact('reason', 'context');
    };
    $issuer = fn (): array => ['issued' => true];

    expect($service->issueSelectedTaxGroupByOrderNoWithTax(
        orderNoWithTax: 'INVALID',
        orderResolver: fn (): ?Model => $order,
        orderDetailsResolver: fn (): array => [],
        issuer: $issuer,
        failure: $failure,
    ))->toBe([])
        ->and($service->issueSelectedTaxGroupByOrderNoWithTax(
            orderNoWithTax: 'MISSING-1',
            orderResolver: fn (): ?Model => null,
            orderDetailsResolver: fn (): array => [],
            issuer: $issuer,
            failure: $failure,
        ))->toBe([])
        ->and($service->issueSelectedTaxGroupByOrderNoWithTax(
            orderNoWithTax: 'OD10007-2',
            orderResolver: fn (): ?Model => $order,
            orderDetailsResolver: fn (): array => [
                1 => [
                    ['title' => 'Taxed item', 'sales_price' => 300, 'qty' => 2],
                ],
            ],
            issuer: $issuer,
            failure: $failure,
        ))->toBe([])
        ->and($failures)->toBe([
            [
                'reason' => 'invalid_order_no_with_tax',
                'context' => ['order_no_with_tax' => 'INVALID'],
            ],
            [
                'reason' => 'order_not_found',
                'context' => ['order_number' => 'MISSING'],
            ],
            [
                'reason' => 'tax_group_not_found',
                'context' => ['order_number' => 'OD10007', 'tax_type' => 2],
            ],
        ]);
});
