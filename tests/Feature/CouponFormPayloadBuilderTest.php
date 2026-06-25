<?php

use Lalalili\CommerceCore\Services\CouponFormPayloadBuilder;

enum CouponFormPayloadBuilderTestType: int
{
    case MEMBER = 1;
    case PROMOTION = 2;
    case SCHEDULED = 11;
    case LINE = 21;
}

it('prepares coupon create payloads with host supplied type buckets and code factories', function (): void {
    $builder = app(CouponFormPayloadBuilder::class);

    $member = $builder->prepareCreate(
        data: ['type' => CouponFormPayloadBuilderTestType::MEMBER, 'title' => '會員券', 'user_id' => 123],
        memberTypes: [CouponFormPayloadBuilderTestType::MEMBER],
        couponCodeFactory: fn (int $type, array $data): string => 'CODE-'.$type.'-'.$data['user_id'],
    );
    $promotion = $builder->prepareCreate(
        data: ['type' => CouponFormPayloadBuilderTestType::PROMOTION, 'title' => '活動券', 'limit_qty' => 50],
        promotionTypes: [CouponFormPayloadBuilderTestType::PROMOTION],
    );
    $scheduled = $builder->prepareCreate(
        data: ['type' => CouponFormPayloadBuilderTestType::SCHEDULED->value, 'title' => '生日禮', 'user_id' => 456],
        scheduledPrefixTypes: [CouponFormPayloadBuilderTestType::SCHEDULED],
        couponCodeFactory: fn (int $type, array $data): string => 'SCH-'.$type.'-'.$data['user_id'],
    );
    $line = $builder->prepareCreate(
        data: ['type' => CouponFormPayloadBuilderTestType::LINE, 'title' => 'LINE綁定'],
        lineTemplateTypes: [CouponFormPayloadBuilderTestType::LINE],
        lineTemplateSequenceFactory: fn (): int => 99,
    );

    expect($member)->toMatchArray([
        'type' => 1,
        'code' => 'CODE-1-123',
    ])->and($promotion)->toMatchArray([
        'type' => 2,
        'left_qty' => 50,
    ])->and($scheduled)->toMatchArray([
        'type' => 11,
        'title' => '排程_生日禮',
        'code' => 'SCH-11-456',
    ])->and($line)->toMatchArray([
        'type' => 21,
        'code' => 'LINE綁定_99',
    ]);
});

it('prepares coupon save payloads for scheduled titles and promotion inventory increases', function (): void {
    $builder = app(CouponFormPayloadBuilder::class);

    $scheduled = $builder->prepareSave(
        data: ['type' => CouponFormPayloadBuilderTestType::SCHEDULED, 'title' => '排程_生日禮'],
        scheduledPrefixTypes: [CouponFormPayloadBuilderTestType::SCHEDULED],
    );
    $promotion = $builder->prepareSave(
        data: ['type' => CouponFormPayloadBuilderTestType::PROMOTION, 'limit_qty' => 3, 'left_qty' => 1, 'increase_qty' => '2'],
        promotionTypes: [CouponFormPayloadBuilderTestType::PROMOTION],
    );
    $unchanged = $builder->prepareSave(
        data: ['type' => CouponFormPayloadBuilderTestType::PROMOTION, 'limit_qty' => 3, 'left_qty' => 1, 'increase_qty' => 0],
        promotionTypes: [CouponFormPayloadBuilderTestType::PROMOTION],
    );

    expect($scheduled)->toMatchArray([
        'type' => 11,
        'title' => '排程_生日禮',
    ])->and($promotion)->toMatchArray([
        'type' => 2,
        'limit_qty' => 5,
        'left_qty' => 3,
    ])->and($promotion)->not->toHaveKey('increase_qty')
        ->and($unchanged)->toMatchArray([
            'type' => 2,
            'limit_qty' => 3,
            'left_qty' => 1,
        ])->and($unchanged)->not->toHaveKey('increase_qty');
});
