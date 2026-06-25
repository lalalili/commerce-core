<?php

use Illuminate\Support\Collection;
use Lalalili\CommerceCore\Services\CartItemAttributeNormalizer;

it('normalizes collection, array, object, and empty attributes', function (): void {
    $normalizer = new CartItemAttributeNormalizer;

    expect($normalizer->normalize(new Collection(['source' => 'campaign'])))
        ->toBe(['source' => 'campaign'])
        ->and($normalizer->normalize(['source' => 'cart']))
        ->toBe(['source' => 'cart'])
        ->and($normalizer->normalize((object) ['source' => 'object']))
        ->toBe(['source' => 'object'])
        ->and($normalizer->normalize(null))
        ->toBe([]);
});

it('reads attributes from cart item like objects', function (): void {
    $normalizer = new CartItemAttributeNormalizer;
    $item = (object) [
        'attributes' => new Collection([
            'additionalPurchases' => true,
        ]),
    ];

    expect($normalizer->fromItem($item))->toBe(['additionalPurchases' => true])
        ->and($normalizer->itemHasKey($item, 'additionalPurchases'))->toBeTrue()
        ->and($normalizer->itemHasKey($item, 'missing'))->toBeFalse();
});
