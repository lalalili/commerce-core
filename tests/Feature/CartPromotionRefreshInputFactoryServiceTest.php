<?php

use Lalalili\CommerceCore\Services\CartPromotionRefreshInputFactoryService;
use Lalalili\CommerceCore\Tests\Support\FakeCartContext;
use Lalalili\CommerceCore\Tests\Support\FakeCartLineContext;
use Lalalili\CommerceCore\Tests\Support\FakeCartPromotionRefreshInput;

it('builds the default promotion refresh cart context', function (): void {
    $context = app(CartPromotionRefreshInputFactoryService::class)->cartContext(FakeCartContext::class);

    expect($context)->toBeInstanceOf(FakeCartContext::class)
        ->and($context->orderTotal)->toBe(0.0)
        ->and($context->allAmount)->toBe(0.0)
        ->and($context->bookAmount)->toBe(0.0)
        ->and($context->ebookAmount)->toBe(0.0)
        ->and($context->specificProductsAmount)->toBe(0.0)
        ->and($context->hasBook)->toBeFalse()
        ->and($context->hasEbook)->toBeFalse()
        ->and($context->hasSpecificProducts)->toBeFalse();
});

it('converts promotion line payloads into discount cart line contexts', function (): void {
    $lines = app(CartPromotionRefreshInputFactoryService::class)->linesFromPayloads([
        [
            'id' => 10,
            'product_id' => 10,
            'quantity' => 2,
            'unit_price' => 150.5,
            'associated_model' => 'Product',
            'attributes' => ['additionalPurchases' => true],
        ],
    ], FakeCartLineContext::class);

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toBeInstanceOf(FakeCartLineContext::class)
        ->and($lines[0]->id)->toBe(10)
        ->and($lines[0]->productId)->toBe(10)
        ->and($lines[0]->quantity)->toBe(2)
        ->and($lines[0]->unitPrice)->toBe(150.5)
        ->and($lines[0]->associatedModel)->toBe('Product')
        ->and($lines[0]->attributes)->toBe(['additionalPurchases' => true]);
});

it('rejects line payloads without product identifiers', function (): void {
    app(CartPromotionRefreshInputFactoryService::class)->lineFromPayload(
        [
            'id' => null,
            'product_id' => null,
            'quantity' => 1,
            'unit_price' => 100.0,
            'associated_model' => 'Product',
            'attributes' => [],
        ],
        FakeCartLineContext::class,
    );
})->throws(UnexpectedValueException::class, 'Cart promotion line payload must include product identifiers.');

it('assembles cart promotion refresh input from host provided lines and promotion sets', function (): void {
    $service = app(CartPromotionRefreshInputFactoryService::class);
    $line = $service->lineFromPayload([
        'id' => 'A100',
        'product_id' => 'A100',
        'quantity' => 1,
        'unit_price' => 99.0,
        'associated_model' => 'Product',
        'attributes' => [],
    ], FakeCartLineContext::class);

    $input = $service->build(
        lines: [$line],
        promotionSetsByProductId: ['A100' => new stdClass],
        giftFulfillment: 'condition_only',
        refreshInputClass: FakeCartPromotionRefreshInput::class,
        cartContextClass: FakeCartContext::class,
    );

    expect($input)->toBeInstanceOf(FakeCartPromotionRefreshInput::class)
        ->and($input->cartContext)->toBeInstanceOf(FakeCartContext::class)
        ->and($input->lines)->toBe([$line])
        ->and($input->promotionSetsByProductId)->toHaveKey('A100')
        ->and($input->giftFulfillment)->toBe('condition_only');
});
