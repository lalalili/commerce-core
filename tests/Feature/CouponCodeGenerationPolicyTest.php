<?php

use Lalalili\CommerceCore\Services\CouponCodeGenerationPolicy;

enum CouponCodeGenerationPolicyTestType: int
{
    case GENERAL = 1;
    case BIRTHDAY = 2;
}

it('normalizes default coupon code generation options', function (): void {
    $policy = app(CouponCodeGenerationPolicy::class)->resolve(typeId: '1');

    expect($policy)->toBe([
        'type_value' => 1,
        'user_id' => null,
        'count' => 1,
        'max_attempts' => 5,
        'should_check_uniqueness' => true,
    ]);
});

it('requires a user id for configured coupon types', function (): void {
    app(CouponCodeGenerationPolicy::class)->resolve(
        typeId: CouponCodeGenerationPolicyTestType::BIRTHDAY,
        requiredUserIdTypes: [CouponCodeGenerationPolicyTestType::BIRTHDAY],
    );
})->throws(InvalidArgumentException::class, 'User id is required for generating coupon number.');

it('keeps the required user id in resolved generation options', function (): void {
    $policy = app(CouponCodeGenerationPolicy::class)->resolve(
        typeId: CouponCodeGenerationPolicyTestType::BIRTHDAY,
        userId: 123,
        count: 3,
        maxAttempts: 7,
        requiredUserIdTypes: [CouponCodeGenerationPolicyTestType::BIRTHDAY],
    );

    expect($policy)->toBe([
        'type_value' => 2,
        'user_id' => 123,
        'count' => 3,
        'max_attempts' => 7,
        'should_check_uniqueness' => true,
    ]);
});

it('disables uniqueness checks and limits attempts for configured coupon types', function (): void {
    $policy = app(CouponCodeGenerationPolicy::class)->resolve(
        typeId: CouponCodeGenerationPolicyTestType::BIRTHDAY,
        userId: 123,
        count: 2,
        maxAttempts: 7,
        uniquenessExemptTypes: [CouponCodeGenerationPolicyTestType::BIRTHDAY],
    );

    expect($policy)->toBe([
        'type_value' => 2,
        'user_id' => 123,
        'count' => 2,
        'max_attempts' => 1,
        'should_check_uniqueness' => false,
    ]);
});

it('rejects invalid generation limits', function (int $count, int $maxAttempts): void {
    app(CouponCodeGenerationPolicy::class)->resolve(
        typeId: CouponCodeGenerationPolicyTestType::GENERAL,
        count: $count,
        maxAttempts: $maxAttempts,
    );
})->with([
    'zero count' => [0, 5],
    'zero attempts' => [1, 0],
])->throws(InvalidArgumentException::class);
