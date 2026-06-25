<?php

use Carbon\CarbonImmutable;
use Lalalili\CommerceCore\Services\ScheduledCouponTemplatePayloadBuilder;

enum ScheduledCouponTemplatePayloadBuilderTestType: int
{
    case MEMBER = 1;
}

it('builds issued member coupon payloads with carbon dates for cptw style hosts', function (): void {
    $now = CarbonImmutable::parse('2026-06-25 10:15:00');
    $builder = app(ScheduledCouponTemplatePayloadBuilder::class);

    $payload = $builder->build(
        template: [
            'title' => '排程_生日禮',
            'amount' => 100,
            'trigger_amount' => 500,
            'scope' => 2,
            'scope_products' => [10, 20],
            'available_days' => 7,
        ],
        userId: 123,
        memberType: ScheduledCouponTemplatePayloadBuilderTestType::MEMBER,
        code: 'BIRTHDAY123',
        now: $now,
        options: [
            'title' => '生日禮',
            'active_column' => 'status',
            'active_value' => 1,
            'created_by' => 'CouponForBirthday',
        ],
    );

    expect($payload['title'])->toBe('生日禮')
        ->and($payload['code'])->toBe('BIRTHDAY123')
        ->and($payload['amount'])->toBe(100)
        ->and($payload['trigger_amount'])->toBe(500)
        ->and($payload['type'])->toBe(1)
        ->and($payload['scope'])->toBe(2)
        ->and($payload['scope_products'])->toBe([10, 20])
        ->and($payload['user_id'])->toBe(123)
        ->and($payload['status'])->toBe(1)
        ->and($payload['created_by'])->toBe('CouponForBirthday')
        ->and($payload['start_date'])->toBe($now)
        ->and($payload['created_at'])->toBe($now)
        ->and($payload['end_date']?->toDateTimeString())->toBe('2026-07-02 10:15:00')
        ->and($payload)->not->toHaveKey('updated_at');
});

it('builds issued member coupon payloads with formatted dates for aitehub style hosts', function (): void {
    $now = CarbonImmutable::parse('2026-06-25 10:15:00');
    $builder = app(ScheduledCouponTemplatePayloadBuilder::class);

    $payload = $builder->build(
        template: [
            'title' => '排程_註冊禮',
            'amount' => 50,
            'trigger_amount' => null,
            'scope' => 3,
            'scope_products' => '[11,22]',
            'available_days' => 3,
        ],
        userId: 456,
        memberType: 1,
        code: 'REGISTER456',
        now: $now,
        options: [
            'title' => '註冊禮',
            'active_column' => 'active',
            'active_value' => 1,
            'created_by' => 1,
            'include_updated_at' => true,
            'format_dates' => true,
            'end_date_end_of_day' => true,
            'decode_scope_products' => true,
        ],
    );

    expect($payload)->toBe([
        'title' => '註冊禮',
        'code' => 'REGISTER456',
        'amount' => 50,
        'start_date' => '2026-06-25 10:15:00',
        'end_date' => '2026-06-28 23:59:59',
        'trigger_amount' => null,
        'type' => 1,
        'scope' => 3,
        'scope_products' => [11, 22],
        'user_id' => 456,
        'created_at' => '2026-06-25 10:15:00',
        'active' => 1,
        'created_by' => 1,
        'updated_at' => '2026-06-25 10:15:00',
    ]);
});

it('can resolve start dates from scheduled template effective days when hosts opt in', function (): void {
    $now = CarbonImmutable::parse('2026-06-25 10:15:00');
    $builder = app(ScheduledCouponTemplatePayloadBuilder::class);

    $payload = $builder->build(
        template: [
            'title' => '排程_兌換禮',
            'amount' => 80,
            'trigger_amount' => 300,
            'scope' => 1,
            'scope_products' => [],
            'available_days' => 7,
            'effective_days' => 2,
        ],
        userId: 789,
        memberType: 1,
        code: 'REDEEM789',
        now: $now,
        options: [
            'title' => '兌換禮',
            'use_effective_days_for_start_date' => true,
        ],
    );

    expect($payload['start_date'])->toEqual($now->addDays(2))
        ->and($builder->resolveStartDate(3, $now))->toEqual($now->addDays(3))
        ->and($builder->resolveStartDate(null, $now))->toBe($now)
        ->and($builder->resolveStartDate(3, '2026-06-25 10:15:00'))->toBe('2026-06-25 10:15:00');
});

it('extracts reusable title suffixes from scheduled template titles', function (?string $title, string $expected): void {
    expect(app(ScheduledCouponTemplatePayloadBuilder::class)->extractTitleSuffix($title))->toBe($expected);
})->with([
    'empty' => [null, ''],
    'plain title' => ['註冊禮', '註冊禮'],
    'scheduled title' => ['排程_生日禮', '生日禮'],
    'empty suffix' => ['排程_', '排程_'],
]);

it('normalizes nullable and json scope products', function (): void {
    $builder = app(ScheduledCouponTemplatePayloadBuilder::class);

    expect($builder->resolveScopeProducts(null))->toBeNull()
        ->and($builder->resolveScopeProducts(''))->toBeNull()
        ->and($builder->resolveScopeProducts('[1,2]', true))->toBe([1, 2])
        ->and($builder->resolveScopeProducts('invalid', true))->toBeNull()
        ->and($builder->resolveScopeProducts('raw', false))->toBe('raw');
});

it('extracts scheduled template payload fields from arrays and objects', function (): void {
    $builder = app(ScheduledCouponTemplatePayloadBuilder::class);

    expect($builder->templatePayload([
        'title' => '排程_註冊禮',
        'amount' => 100,
        'trigger_amount' => 500,
        'scope' => 2,
        'scope_products' => [],
        'available_days' => 7,
        'effective_days' => 1,
    ]))->toBe([
        'title' => '排程_註冊禮',
        'amount' => 100,
        'trigger_amount' => 500,
        'scope' => 2,
        'scope_products' => null,
        'available_days' => 7,
        'effective_days' => 1,
    ]);

    $template = (object) [
        'name' => '排程_生日禮',
        'discount_amount' => 80,
        'threshold_amount' => 300,
        'coupon_scope' => 1,
        'product_ids' => [11, 22],
        'valid_days' => 30,
        'starts_after_days' => 2,
    ];

    expect($builder->templatePayload($template, [
        'title' => 'name',
        'amount' => 'discount_amount',
        'trigger_amount' => 'threshold_amount',
        'scope' => 'coupon_scope',
        'scope_products' => 'product_ids',
        'available_days' => 'valid_days',
        'effective_days' => 'starts_after_days',
    ]))->toBe([
        'title' => '排程_生日禮',
        'amount' => 80,
        'trigger_amount' => 300,
        'scope' => 1,
        'scope_products' => [11, 22],
        'available_days' => 30,
        'effective_days' => 2,
    ]);
});

it('prepares issued coupon payload arrays for insert operations', function (): void {
    $builder = app(ScheduledCouponTemplatePayloadBuilder::class);

    expect($builder->preparePayloadForInsert([
        'code' => 'BIRTHDAY-1',
        'scope_products' => [11, '22'],
        'metadata' => ['campaign' => 'birthday'],
    ], ['scope_products', 'metadata']))->toBe([
        'code' => 'BIRTHDAY-1',
        'scope_products' => '[11,"22"]',
        'metadata' => '{"campaign":"birthday"}',
    ]);

    expect($builder->preparePayloadsForInsert([
        [
            'code' => 'BIRTHDAY-2',
            'scope_products' => null,
        ],
        [
            'code' => 'BIRTHDAY-3',
            'scope_products' => [33],
        ],
        [
            'code' => 'BIRTHDAY-4',
            'scope_products' => '[44]',
        ],
    ]))->toBe([
        [
            'code' => 'BIRTHDAY-2',
            'scope_products' => null,
        ],
        [
            'code' => 'BIRTHDAY-3',
            'scope_products' => '[33]',
        ],
        [
            'code' => 'BIRTHDAY-4',
            'scope_products' => '[44]',
        ],
    ]);
});
