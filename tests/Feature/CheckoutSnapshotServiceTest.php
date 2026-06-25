<?php

use Illuminate\Support\Collection;
use Lalalili\CommerceCore\Services\CheckoutSnapshotService;

enum CheckoutSnapshotFakeProductType: int
{
    case Course = 3;
}

it('captures cart items, conditions, totals, and a stable hash', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $cart = new CheckoutSnapshotFakeCart(
        items: [
            new CheckoutSnapshotFakeItem(
                id: 'SKU-1',
                name: 'Package &amp; product',
                price: '100.50',
                quantity: 2,
                attributes: [
                    'prod_no' => 'P001',
                    'ignored' => 'hidden',
                ],
                conditions: [
                    new CheckoutSnapshotFakeCondition('Campaign', 'discount', '-10', [
                        'event_id' => 7,
                        'ignored' => 'hidden',
                    ]),
                ],
            ),
        ],
        conditions: new Collection([
            new CheckoutSnapshotFakeCondition('Coupon', 'coupon', '-20', [
                'sort' => 1,
                'ignored' => 'hidden',
            ]),
        ]),
        subtotal: '201',
        total: '171.5',
    );

    $snapshot = $service->captureCart($cart, ['prod_no']);
    $sameDataDifferentOrder = [
        'total' => 171.5,
        'subtotal' => 201,
        'conditions' => $snapshot['conditions'],
        'items' => $snapshot['items'],
    ];

    expect($snapshot)
        ->toHaveKeys(['items', 'conditions', 'subtotal', 'total', 'hash'])
        ->and($snapshot['items'][0]['name'])->toBe('Package & product')
        ->and($snapshot['items'][0]['price'])->toBe(100.5)
        ->and($snapshot['items'][0]['quantity'])->toBe(2)
        ->and($snapshot['items'][0]['price_with_conditions'])->toBe(90.5)
        ->and($snapshot['items'][0]['price_sum_with_conditions'])->toBe(181)
        ->and($snapshot['items'][0]['attributes'])->toBe(['prod_no' => 'P001'])
        ->and($snapshot['items'][0]['conditions'][0]['attributes'])->toBe(['event_id' => 7])
        ->and($snapshot['conditions'][0]['attributes'])->toBe(['sort' => 1])
        ->and($snapshot['subtotal'])->toBe(201)
        ->and($snapshot['total'])->toBe(171.5)
        ->and($snapshot['hash'])->toBe($service->hashPayload($sameDataDifferentOrder));
});

it('normalizes checkout line snapshots from a host supplied schema', function (): void {
    $service = app(CheckoutSnapshotService::class);

    $lines = $service->normalizeLineSnapshots([
        [
            'product_number' => 1001,
            'product_title' => 'Book',
            'quantity' => '2',
            'sales_price' => '450',
            'event_id' => [5],
            'is_preorder' => 1,
            'preorder_release_at' => now()->setDate(2026, 1, 2)->setTime(3, 4, 5),
        ],
    ], [
        'product_number' => ['type' => 'string', 'default' => ''],
        'product_title' => ['type' => 'string', 'default' => ''],
        'quantity' => ['type' => 'int', 'default' => 0],
        'sales_price' => ['type' => 'string', 'default' => ''],
        'event_id' => ['type' => 'array', 'default' => []],
        'is_preorder' => ['type' => 'bool', 'default' => false],
        'preorder_release_at' => ['type' => 'datetime', 'default' => null],
    ]);

    expect($lines)->toBe([
        [
            'product_number' => '1001',
            'product_title' => 'Book',
            'quantity' => 2,
            'sales_price' => '450',
            'event_id' => [5],
            'is_preorder' => true,
            'preorder_release_at' => '2026-01-02 03:04:05',
        ],
    ])
        ->and($service->detailTotal($lines))->toBe(900);
});

it('extracts reusable cart line values for host line builders', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $items = [
        new CheckoutSnapshotFakeItem(
            id: 'SKU-1',
            name: 'Course &amp; Bundle',
            price: '1000.40',
            quantity: 2,
            attributes: [],
            conditions: [
                new CheckoutSnapshotFakeCondition('Event A', 'rebate', '-100', [
                    'event_id' => 11,
                    'event_title' => 'Launch',
                ]),
                new CheckoutSnapshotFakeCondition('Event B', 'rebate', '-50', [
                    'event_id' => 12,
                    'event_title' => 'Bundle',
                ]),
            ],
        ),
        new CheckoutSnapshotFakeItem(
            id: 'SKU-2',
            name: 'Ignored',
            price: '300',
            quantity: 0,
            attributes: [],
            conditions: [],
        ),
    ];

    $attributes = $service->cartLineConditionAttributes($items[0]);
    $baseValues = $service->cartLineBaseValues($items[0]);

    expect($service->expectedCartLineCount($items))->toBe(1)
        ->and($service->expectedCartLineCount(null))->toBe(0)
        ->and($attributes)->toBe([
            'event_id' => [11, 12],
            'event_title' => ['Launch', 'Bundle'],
        ])
        ->and($baseValues)->toBe([
            'id' => 'SKU-1',
            'title' => 'Course &amp; Bundle',
            'decoded_title' => 'Course & Bundle',
            'quantity' => 2,
            'list_price' => '1000',
            'sales_price' => '990',
            'condition_attributes' => [
                'event_id' => [11, 12],
                'event_title' => ['Launch', 'Bundle'],
            ],
        ])
        ->and($service->cartItemPriceWithConditions($items[0]))->toBe(990.4)
        ->and($service->moneyString($items[0]->price))->toBe('1000')
        ->and($service->moneyString($service->cartItemPriceWithConditions($items[0])))->toBe('990')
        ->and($service->enumValue(CheckoutSnapshotFakeProductType::Course))->toBe(3);
});

it('builds cart line snapshots with host supplied mappers', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $items = [
        new CheckoutSnapshotFakeItem(
            id: 'SKU-1',
            name: 'Course &amp; Bundle',
            price: '1000.40',
            quantity: 2,
            attributes: [
                'company_id' => 7,
            ],
            conditions: [
                new CheckoutSnapshotFakeCondition('Event A', 'rebate', '-100', [
                    'event_id' => 11,
                    'event_title' => 'Launch',
                ]),
            ],
        ),
        new CheckoutSnapshotFakeItem(
            id: '',
            name: 'Skipped',
            price: '300',
            quantity: 1,
            attributes: [],
            conditions: [],
        ),
    ];

    $snapshots = $service->cartLineSnapshots(
        $items,
        static function (mixed $item, array $baseValues, CheckoutSnapshotService $snapshots): ?array {
            $companyId = data_get($item, 'attributes.company_id');
            if ($baseValues['id'] === '' || ! is_numeric($companyId)) {
                return null;
            }

            return [
                'product_id' => $baseValues['id'],
                'company_id' => (int) $companyId,
                'quantity' => $baseValues['quantity'],
                'title' => $baseValues['title'],
                'decoded_title' => $baseValues['decoded_title'],
                'sales_price' => $baseValues['sales_price'],
                'event_id' => $baseValues['condition_attributes']['event_id'] ?? [],
                'helper_total' => $snapshots->detailTotal([
                    [
                        'sales_price' => $baseValues['sales_price'],
                        'quantity' => $baseValues['quantity'],
                    ],
                ]),
            ];
        },
    );

    expect($snapshots)->toBe([
        [
            'product_id' => 'SKU-1',
            'company_id' => 7,
            'quantity' => 2,
            'title' => 'Course &amp; Bundle',
            'decoded_title' => 'Course & Bundle',
            'sales_price' => '990',
            'event_id' => [11],
            'helper_total' => 1980,
        ],
    ])
        ->and($service->cartLineSnapshots(null, static fn (): array => []))->toBe([]);
});

it('checks whether host cart line snapshots cover every checkout line', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $items = [
        new CheckoutSnapshotFakeItem(id: 'SKU-1', name: 'Course', price: 1000, quantity: 2, attributes: [], conditions: []),
        new CheckoutSnapshotFakeItem(id: 'SKU-2', name: 'Book', price: 500, quantity: 1, attributes: [], conditions: []),
        new CheckoutSnapshotFakeItem(id: 'SKU-3', name: 'Ignored', price: 300, quantity: 0, attributes: [], conditions: []),
    ];

    expect($service->hasCompleteLineSnapshots([
        ['product_id' => 'SKU-1', 'quantity' => 2],
        ['product_id' => 'SKU-2', 'quantity' => 1],
    ], $items))->toBeTrue()
        ->and($service->hasCompleteLineSnapshots([], $items))->toBeFalse()
        ->and($service->hasCompleteLineSnapshots([
            ['product_id' => 'SKU-1', 'quantity' => 2],
        ], $items))->toBeFalse()
        ->and($service->hasCompleteLineSnapshots([
            ['product_id' => 'SKU-1', 'quantity' => 2],
        ], null))->toBeFalse();
});

it('calculates checkout totals in host compatible formats', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $cart = new CheckoutSnapshotFakeCart(
        items: [],
        conditions: [],
        subtotal: '2300',
        total: '1750',
    );

    expect($service->checkoutTotals($cart))->toBe([
        'total_sales_price' => 1750,
        'total_discount_amt' => 550,
    ])
        ->and($service->checkoutTotals($cart, stringValues: true))->toBe([
            'total_sales_price' => '1750',
            'total_discount_amt' => '550',
        ]);
});

it('summarizes checkout consistency for host order guards', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $cart = new CheckoutSnapshotFakeCart(
        items: [
            new CheckoutSnapshotFakeItem(id: 'SKU-1', name: 'Course', price: 1000, quantity: 1, attributes: [], conditions: []),
        ],
        conditions: [
            new CheckoutSnapshotFakeCondition('Member', 'member_coupon', '-100', []),
            new CheckoutSnapshotFakeCondition('Promotion', 'promotion_coupon', '-50', []),
        ],
        subtotal: '1000',
        total: '850',
    );

    expect($service->checkoutConsistencySummary(
        cart: $cart,
        cartContent: $cart->getContent(),
        hasMemberCouponCode: true,
        hasPromotionCouponCode: true,
        stringValues: true,
    ))->toBe([
        'totals' => [
            'total_sales_price' => '850',
            'total_discount_amt' => '150',
        ],
        'has_cart_items' => true,
        'has_positive_total' => true,
        'coupon_condition_mismatch' => false,
        'is_ready' => true,
    ])
        ->and($service->checkoutConsistencySummary(
            cart: $cart,
            cartContent: [],
            hasMemberCouponCode: false,
            hasPromotionCouponCode: true,
        ))->toMatchArray([
            'has_cart_items' => false,
            'coupon_condition_mismatch' => true,
            'is_ready' => false,
        ])
        ->and($service->checkoutConsistencySummary(
            cart: new CheckoutSnapshotFakeCart(items: $cart->getContent(), conditions: [], subtotal: 1000, total: 0),
            cartContent: $cart->getContent(),
            hasMemberCouponCode: false,
            hasPromotionCouponCode: false,
        ))->toMatchArray([
            'totals' => [
                'total_sales_price' => 0,
                'total_discount_amt' => 1000,
            ],
            'has_positive_total' => false,
            'is_ready' => false,
        ]);
});

it('reports reusable checkout guard failure reasons', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $items = [
        new CheckoutSnapshotFakeItem(id: 'SKU-1', name: 'Course', price: 1000, quantity: 1, attributes: [], conditions: []),
    ];

    expect($service->checkoutConsistencyFailure([
        'has_cart_items' => true,
        'has_positive_total' => true,
        'coupon_condition_mismatch' => false,
    ]))->toBeNull()
        ->and($service->checkoutConsistencyFailure([
            'has_cart_items' => false,
            'has_positive_total' => true,
            'coupon_condition_mismatch' => false,
        ]))->toBe(CheckoutSnapshotService::CHECKOUT_FAILURE_CART_ITEMS)
        ->and($service->checkoutConsistencyFailure([
            'has_cart_items' => true,
            'has_positive_total' => false,
            'coupon_condition_mismatch' => false,
        ]))->toBe(CheckoutSnapshotService::CHECKOUT_FAILURE_CART_ITEMS)
        ->and($service->checkoutConsistencyFailure([
            'has_cart_items' => true,
            'has_positive_total' => true,
            'coupon_condition_mismatch' => true,
        ]))->toBe(CheckoutSnapshotService::CHECKOUT_FAILURE_COUPON_CONDITION_MISMATCH)
        ->and($service->cartLineSnapshotFailure([
            ['product_id' => 'SKU-1', 'quantity' => 1],
        ], $items))->toBeNull()
        ->and($service->cartLineSnapshotFailure([], $items))->toBe(CheckoutSnapshotService::CHECKOUT_FAILURE_LINE_SNAPSHOTS);
});

it('detects coupon code and cart condition mismatches', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $memberConditions = [
        new CheckoutSnapshotFakeCondition('Member', 'member_coupon', '-100', []),
    ];
    $promotionConditions = [
        new CheckoutSnapshotFakeCondition('Promotion', 'promotion_coupon', '-50', []),
    ];

    expect($service->couponConditionMismatch(
        hasMemberCouponCode: true,
        memberCouponConditions: $memberConditions,
        hasPromotionCouponCode: true,
        promotionCouponConditions: $promotionConditions,
    ))->toBeFalse()
        ->and($service->couponConditionMismatch(true, [], false, []))->toBeTrue()
        ->and($service->couponConditionMismatch(false, $memberConditions, false, []))->toBeTrue()
        ->and($service->couponConditionMismatch(false, [], true, []))->toBeTrue()
        ->and($service->couponConditionMismatch(false, [], false, $promotionConditions))->toBeTrue();
});

it('builds order detail rows from host schemas', function (): void {
    $service = app(CheckoutSnapshotService::class);

    $lineSnapshots = [
        [
            'product_number' => 'P001',
            'product_title' => 'Course',
            'quantity' => '2',
            'list_price' => '1000',
            'sales_price' => '850',
            'is_preorder' => true,
            'preorder_release_at' => '2026-07-01',
            'event_id' => [11, 12],
            'event_title' => ['夏季活動', 'Bundle'],
        ],
        [
            'product_number' => '',
            'product_title' => 'Skipped',
            'quantity' => 1,
        ],
        [
            'product_number' => 'P003',
            'product_title' => 'Zero quantity',
            'quantity' => 0,
        ],
    ];

    $rows = $service->orderDetailRows(
        $lineSnapshots,
        [
            'product_number' => ['type' => 'string', 'default' => ''],
            'product_title' => ['type' => 'string', 'default' => ''],
            'qty' => ['source' => 'quantity', 'type' => 'int', 'default' => 0],
            'list_price' => ['type' => 'raw', 'default' => 0],
            'sales_price' => ['type' => 'raw', 'default' => 0],
            'is_preorder' => ['type' => 'bool', 'default' => false],
            'preorder_release_at' => ['type' => 'raw', 'default' => null],
            'event_id' => ['type' => 'json', 'default' => []],
            'event_title' => ['type' => 'json', 'default' => []],
        ],
        [
            'order_id' => 99,
            'order_number' => 'O-001',
            'created_by' => 5,
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
        ],
        productKey: 'product_number',
    );

    expect($rows)->toBe([
        [
            'order_id' => 99,
            'order_number' => 'O-001',
            'created_by' => 5,
            'created_at' => '2026-06-25 10:00:00',
            'updated_at' => '2026-06-25 10:00:00',
            'product_number' => 'P001',
            'product_title' => 'Course',
            'qty' => 2,
            'list_price' => '1000',
            'sales_price' => '850',
            'is_preorder' => true,
            'preorder_release_at' => '2026-07-01',
            'event_id' => '[11,12]',
            'event_title' => '["夏季活動","Bundle"]',
        ],
    ]);
});

it('keeps array values in order detail rows when the host schema requires arrays', function (): void {
    $service = app(CheckoutSnapshotService::class);

    $rows = $service->orderDetailRows([
        [
            'product_id' => 10,
            'product_type' => CheckoutSnapshotFakeProductType::Course->value,
            'company_id' => 3,
            'title' => 'Course',
            'quantity' => 1,
            'list_price' => 1200,
            'sales_price' => 1000,
            'event_id' => [11],
            'event_title' => ['Launch'],
        ],
    ], [
        'product_id' => ['type' => 'raw', 'default' => null],
        'product_type' => ['type' => 'raw', 'default' => null],
        'company_id' => ['type' => 'raw', 'default' => null],
        'title' => ['type' => 'string', 'default' => ''],
        'qty' => ['source' => 'quantity', 'type' => 'int', 'default' => 0],
        'list_price' => ['type' => 'raw', 'default' => 0],
        'sales_price' => ['type' => 'raw', 'default' => 0],
        'event_id' => ['type' => 'array', 'default' => []],
        'event_title' => ['type' => 'array', 'default' => []],
    ], [
        'order_id' => 99,
        'order_number' => 'O-001',
    ]);

    expect($rows)->toBe([
        [
            'order_id' => 99,
            'order_number' => 'O-001',
            'product_id' => 10,
            'product_type' => 3,
            'company_id' => 3,
            'title' => 'Course',
            'qty' => 1,
            'list_price' => 1200,
            'sales_price' => 1000,
            'event_id' => [11],
            'event_title' => ['Launch'],
        ],
    ])
        ->and($service->detailTotal($rows, 'list_price', 'qty'))->toBe(1200);
});

it('summarizes preorder line snapshots for host order payloads', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $earlyRelease = now()->setDate(2026, 8, 1)->setTime(10, 0);
    $lateRelease = now()->setDate(2026, 9, 1)->setTime(10, 0);

    $summary = $service->preorderSummary([
        [
            'is_preorder' => false,
            'preorder_release_at' => $lateRelease,
        ],
        [
            'is_preorder' => true,
            'preorder_release_at' => $earlyRelease,
        ],
        [
            'is_preorder' => true,
            'preorder_release_at' => $lateRelease,
        ],
        [
            'is_preorder' => true,
            'preorder_release_at' => 'not-a-date',
        ],
    ]);

    expect($summary)->toBe([
        'has_preorder' => true,
        'preorder_hold_until' => $lateRelease,
    ])
        ->and($service->preorderSummary([]))->toBe([
            'has_preorder' => false,
            'preorder_hold_until' => null,
        ]);
});

it('builds condition discount notes for host order notes', function (): void {
    $service = app(CheckoutSnapshotService::class);

    $notes = $service->conditionDiscountNotes(new Collection([
        new CheckoutSnapshotFakeCondition('Launch', 'rebate', '-100', []),
        new CheckoutSnapshotFakeCondition('Bundle', 'rebate', '-50.5', []),
    ]));

    expect($notes)->toBe('活動類折扣:Launch-$100,活動類折扣:Bundle-$50.5,')
        ->and($service->conditionDiscountNotes(null))->toBe('');
});

it('builds reusable checkout snapshot persistence attributes', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $capturedAt = now()->setDate(2026, 6, 25)->setTime(10, 30);
    $payload = [
        'order' => ['number' => 'O-001'],
        'line_snapshots' => [['product_number' => 'P001']],
    ];

    $attributes = $service->checkoutSnapshotAttributes(
        orderId: 99,
        userId: 5,
        lineSnapshots: [
            ['sales_price' => 850, 'quantity' => 2],
        ],
        cartSnapshot: [
            'total' => 1700,
            'hash' => 'cart-hash',
        ],
        payload: $payload,
        capturedAt: $capturedAt,
        extra: [
            'submission_id' => 'SUB-001',
        ],
    );

    expect($attributes)->toBe([
        'order_id' => 99,
        'user_id' => 5,
        'snapshot_version' => 1,
        'line_count' => 1,
        'cart_total' => 1700,
        'detail_total' => 1700,
        'payload' => $payload,
        'cart_hash' => 'cart-hash',
        'payload_hash' => $service->hashPayload($payload),
        'captured_at' => $capturedAt,
        'submission_id' => 'SUB-001',
    ]);
});

it('builds reusable checkout success payloads from host snapshots', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $capturedAt = now()->setDate(2026, 6, 25)->setTime(11, 15);
    $orderSnapshot = [
        'id' => 99,
        'number' => 'O-001',
    ];
    $requestSnapshot = [
        'payment_type' => 'credit-card',
    ];
    $cartSnapshot = [
        'total' => 1700,
    ];
    $lineSnapshots = [
        [
            'product_number' => 'P001',
            'quantity' => 2,
        ],
    ];

    $payload = $service->checkoutSuccessPayload(
        orderSnapshot: $orderSnapshot,
        requestSnapshot: $requestSnapshot,
        cartSnapshot: $cartSnapshot,
        lineSnapshots: $lineSnapshots,
        detailTotal: 1700,
        capturedAt: $capturedAt,
        extra: [
            'source' => 'checkout',
        ],
    );

    expect($payload)->toBe([
        'snapshot_version' => 1,
        'captured_at' => '2026-06-25 11:15:00',
        'order' => $orderSnapshot,
        'request' => $requestSnapshot,
        'checkout_cart' => $cartSnapshot,
        'line_snapshots' => $lineSnapshots,
        'detail_total' => 1700,
        'source' => 'checkout',
    ]);
});

it('builds reusable checkout failure log context', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $capturedAt = now()->setDate(2026, 6, 25)->setTime(11, 45);
    $exception = new RuntimeException('Checkout failed');

    $context = $service->checkoutFailureContext(
        exception: $exception,
        requestSnapshot: [
            'payment_type' => 'credit-card',
        ],
        cartContext: [
            'capture_failed' => true,
            'message' => 'checkout cart unavailable',
        ],
        capturedAt: $capturedAt,
        extra: [
            'order_number' => 'O-001',
        ],
    );

    expect($context)->toBe([
        'snapshot_version' => 1,
        'captured_at' => '2026-06-25 11:45:00',
        'exception' => RuntimeException::class,
        'exception_message' => 'Checkout failed',
        'request' => [
            'payment_type' => 'credit-card',
        ],
        'checkout_cart' => [
            'capture_failed' => true,
            'message' => 'checkout cart unavailable',
        ],
        'order_number' => 'O-001',
    ]);
});

it('reads request values with safe fallbacks for host snapshots', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $request = new CheckoutSnapshotFakeRequest([
        'payment_type' => 'credit-card',
        'mobile_code' => '',
        'donation_code' => '123',
    ]);
    $throwingRequest = new CheckoutSnapshotThrowingRequest;

    expect($service->requestInput($request, 'payment_type'))->toBe('credit-card')
        ->and($service->requestInput($request, 'missing', 'fallback'))->toBe('fallback')
        ->and($service->requestInput(new stdClass, 'missing', 'fallback'))->toBe('fallback')
        ->and($service->requestInput($throwingRequest, 'payment_type', 'fallback'))->toBe('fallback')
        ->and($service->requestFilled($request, 'donation_code'))->toBeTrue()
        ->and($service->requestFilled($request, 'mobile_code'))->toBeFalse()
        ->and($service->requestFilled(new stdClass, 'missing', true))->toBeTrue()
        ->and($service->requestFilled($throwingRequest, 'payment_type', true))->toBeTrue();
});

it('builds request snapshots from host supplied field schemas', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $request = new CheckoutSnapshotFakeRequest([
        'submission_id' => 'abc-123',
        'pay_type' => 'credit_card',
        'mobile_code' => '',
        'triplicate_number' => '12345678',
    ]);

    expect($service->requestSnapshot($request, [
        'submission_id' => ['default' => ''],
        'payment_type' => 'pay_type',
        'has_mobile_code' => ['source' => 'mobile_code', 'filled' => true],
        'has_triplicate_number' => ['source' => 'triplicate_number', 'filled' => true],
        'missing' => ['default' => 'fallback'],
    ]))->toBe([
        'submission_id' => 'abc-123',
        'payment_type' => 'credit_card',
        'has_mobile_code' => false,
        'has_triplicate_number' => true,
        'missing' => 'fallback',
    ])
        ->and($service->requestSnapshot(new CheckoutSnapshotThrowingRequest, [
            'payment_type' => ['default' => 'fallback'],
            'has_mobile_code' => ['source' => 'mobile_code', 'filled' => true, 'default' => true],
        ]))->toBe([
            'payment_type' => 'fallback',
            'has_mobile_code' => true,
        ]);
});

it('builds reusable checkout success snapshot records', function (): void {
    $service = app(CheckoutSnapshotService::class);
    $capturedAt = now()->setDate(2026, 6, 25)->setTime(12, 5);
    $request = new CheckoutSnapshotFakeRequest([
        'pay_type' => 'credit_card',
        'member_coupon_code' => 'MEMBER10',
    ]);
    $cart = new CheckoutSnapshotFakeCart(
        items: [
            new CheckoutSnapshotFakeItem(
                id: 'P001',
                name: 'Test Product',
                price: 500,
                quantity: 2,
                attributes: ['prod_no' => 'P001', 'ignored' => 'value'],
                conditions: [],
            ),
        ],
        conditions: [],
        subtotal: 1000,
        total: 900,
    );

    $record = $service->checkoutSuccessSnapshotRecord(
        request: $request,
        cart: $cart,
        order: [
            'number' => 'O-001',
            'total_sales_price' => '900',
            'total_discount_amt' => '100',
            'payment_type' => 'credit_card',
            'submission_id' => 'SUB-1',
        ],
        orderId: 99,
        userId: 5,
        lineSnapshots: [
            [
                'product_number' => 'P001',
                'quantity' => 2,
                'sales_price' => '450',
            ],
        ],
        capturedAt: $capturedAt,
        orderSchema: [
            'number',
            'submission_id',
            'total_sales_price',
            'total_discount_amt',
            'payment_type',
        ],
        requestSchema: [
            'payment_type' => 'pay_type',
        ],
        lineSnapshotSchema: [
            'product_number' => ['type' => 'string', 'default' => ''],
            'quantity' => ['type' => 'int', 'default' => 0],
            'sales_price' => ['type' => 'string', 'default' => ''],
        ],
        options: [
            'cart_item_attribute_keys' => ['prod_no'],
            'order_request_schema' => [
                'member_coupon_code',
            ],
            'attribute_extra' => [
                'submission_id' => 'SUB-1',
            ],
            'payload_extra' => [
                'source' => 'checkout',
            ],
        ],
    );

    expect($record['lookup'])->toBe([
        'order_number' => 'O-001',
    ])
        ->and($record['detail_total'])->toBe(900)
        ->and($record['line_snapshots'])->toBe([
            [
                'product_number' => 'P001',
                'quantity' => 2,
                'sales_price' => '450',
            ],
        ])
        ->and($record['payload']['order'])->toBe([
            'id' => 99,
            'user_id' => 5,
            'number' => 'O-001',
            'submission_id' => 'SUB-1',
            'total_sales_price' => '900',
            'total_discount_amt' => '100',
            'payment_type' => 'credit_card',
            'member_coupon_code' => 'MEMBER10',
        ])
        ->and($record['payload']['request'])->toBe([
            'payment_type' => 'credit_card',
        ])
        ->and($record['payload']['source'])->toBe('checkout')
        ->and($record['attributes']['order_id'])->toBe(99)
        ->and($record['attributes']['user_id'])->toBe(5)
        ->and($record['attributes']['line_count'])->toBe(1)
        ->and($record['attributes']['detail_total'])->toBe(900)
        ->and($record['attributes']['submission_id'])->toBe('SUB-1')
        ->and($record['cart_snapshot']['items'][0]['attributes'])->toBe([
            'prod_no' => 'P001',
        ]);
});

class CheckoutSnapshotFakeCart
{
    public function __construct(
        private readonly array $items,
        private readonly mixed $conditions,
        private readonly mixed $subtotal,
        private readonly mixed $total,
    ) {}

    public function getContent(): array
    {
        return $this->items;
    }

    public function getConditions(): mixed
    {
        return $this->conditions;
    }

    public function getConditionsByType(string $type): array
    {
        return collect($this->conditions)
            ->filter(fn (mixed $condition): bool => $condition instanceof CheckoutSnapshotFakeCondition && $condition->getType() === $type)
            ->values()
            ->all();
    }

    public function getSubTotal(bool $formatted = false): mixed
    {
        return $this->subtotal;
    }

    public function getTotal(bool $formatted = false): mixed
    {
        return $this->total;
    }
}

class CheckoutSnapshotFakeRequest
{
    public function __construct(private readonly array $input) {}

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    public function filled(string $key): bool
    {
        return ($this->input[$key] ?? null) !== null && $this->input[$key] !== '';
    }
}

class CheckoutSnapshotThrowingRequest
{
    public function input(string $key, mixed $default = null): mixed
    {
        throw new RuntimeException('request unavailable');
    }

    public function filled(string $key): bool
    {
        throw new RuntimeException('request unavailable');
    }
}

class CheckoutSnapshotFakeItem
{
    public function __construct(
        public readonly mixed $id,
        public readonly mixed $name,
        public readonly mixed $price,
        public readonly mixed $quantity,
        public readonly mixed $attributes,
        public readonly mixed $conditions,
    ) {}

    public function getPriceWithConditions(bool $formatted = false): mixed
    {
        return (float) $this->price - 10;
    }

    public function getPriceSumWithConditions(bool $formatted = false): mixed
    {
        return ((float) $this->price - 10) * (int) $this->quantity;
    }
}

class CheckoutSnapshotFakeCondition
{
    public function __construct(
        private readonly mixed $name,
        private readonly mixed $type,
        private readonly mixed $value,
        private readonly array $attributes,
    ) {}

    public function getName(): mixed
    {
        return $this->name;
    }

    public function getType(): mixed
    {
        return $this->type;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
