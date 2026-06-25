<?php

use Lalalili\CommerceCore\Services\CouponEligibilityCartDataFactory;

it('builds coupon eligibility cart amount buckets from generic lines', function (): void {
    $data = app(CouponEligibilityCartDataFactory::class)->fromLines(
        lines: [
            ['id' => 10, 'type' => 1, 'list_price' => 300, 'quantity' => 2],
            ['id' => 20, 'type' => 2, 'list_price' => 500, 'quantity' => 1],
            ['id' => 30, 'type' => 9, 'list_price' => 120, 'quantity' => 1],
        ],
        bookTypes: [1],
        ebookTypes: [2],
        specificProductIds: [20],
        orderTotal: 900.0,
    );

    expect($data)->toMatchArray([
        'order_total' => 900.0,
        'all_amount' => 1220.0,
        'book_amount' => 600.0,
        'ebook_amount' => 500.0,
        'specific_products_amount' => 500.0,
        'has_book' => true,
        'has_ebook' => true,
        'has_specific_products' => true,
    ]);
});

it('uses line totals as order total when no explicit total is provided', function (): void {
    $data = app(CouponEligibilityCartDataFactory::class)->fromLines(
        lines: [
            ['id' => 'A', 'type' => 'course', 'list_price' => 250, 'cart_quantity' => 3],
            ['id' => 'B', 'type' => 'ebook', 'list_price' => 100, 'quantity' => 0],
        ],
        bookTypes: ['course'],
        ebookTypes: ['ebook'],
    );

    expect($data['order_total'])->toBe(850.0)
        ->and($data['all_amount'])->toBe(850.0)
        ->and($data['book_amount'])->toBe(750.0)
        ->and($data['ebook_amount'])->toBe(100.0)
        ->and($data['specific_products_amount'])->toBe(0.0)
        ->and($data['has_specific_products'])->toBeFalse();
});

it('ignores lines without a usable id', function (): void {
    $data = app(CouponEligibilityCartDataFactory::class)->fromLines(
        lines: [
            ['type' => 1, 'list_price' => 999],
            ['id' => 5, 'type' => 1, 'list_price' => 200],
        ],
        bookTypes: [1],
        ebookTypes: [],
    );

    expect($data['all_amount'])->toBe(200.0)
        ->and($data['book_amount'])->toBe(200.0);
});
