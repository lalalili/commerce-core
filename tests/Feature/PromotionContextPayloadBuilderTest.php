<?php

use Illuminate\Support\Carbon;
use Lalalili\CommerceCore\Services\PromotionContextPayloadBuilder;

enum PromotionContextPayloadBuilderTestType: int
{
    case FixedDiscount = 901;
}

it('builds promotion context payloads from event like objects', function (): void {
    $service = new PromotionContextPayloadBuilder;
    $updatedAt = Carbon::parse('2026-06-25 12:00:00');

    expect($service->payload((object) [
        'id' => '15',
        'type' => PromotionContextPayloadBuilderTestType::FixedDiscount,
        'sort' => '3',
        'title' => 'Summer Sale',
        'discount_amount' => '100',
        'rebate_get_amount' => '25.5',
        'rebate_trigger_amount' => '500',
        'gift_trigger_amount' => '',
        'gift_trigger_quantity' => '2',
        'gift_prod_no' => 'GIFT-001',
        'repeatable' => true,
        'updated_at' => $updatedAt,
    ]))->toBe([
        'type' => 901,
        'sort' => 3,
        'discountAmount' => 100,
        'rebateGetAmount' => 25.5,
        'eventId' => 15,
        'name' => 'Summer Sale',
        'rebateTriggerAmount' => 500,
        'giftTriggerAmount' => null,
        'giftTriggerQuantity' => 2,
        'giftProductCode' => 'GIFT-001',
        'repeatable' => true,
        'attributes' => [
            'updated_at_timestamp' => $updatedAt->timestamp,
            'sort' => 3,
        ],
    ]);
});

it('supports host-specific gift product keys and optional sort attributes', function (): void {
    $service = new PromotionContextPayloadBuilder;

    $payload = $service->payload([
        'id' => 7,
        'type' => '902',
        'sort' => 1,
        'product_id' => 1234,
        'repeatable' => '0',
    ], giftProductKey: 'product_id', includeSortAttribute: false);

    expect($payload)->not->toBeNull()
        ->and($payload['giftProductCode'])->toBe('1234')
        ->and($payload['repeatable'])->toBe(0)
        ->and($payload['attributes'])->toBe([
            'updated_at_timestamp' => null,
        ]);
});

it('returns null for events without a numeric type', function (): void {
    $service = new PromotionContextPayloadBuilder;

    expect($service->payload(['type' => 'unknown']))->toBeNull();
});
