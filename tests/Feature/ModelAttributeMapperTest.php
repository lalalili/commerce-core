<?php

use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;

it('resolves configured columns and treats blank config as unmapped', function (): void {
    $mapper = new ModelAttributeMapper;

    // Default passthrough when nothing special is configured.
    expect($mapper->column('products', 'title', 'title'))->toBe('title');

    // Null in config (order_details.product_number) means "no such column".
    expect($mapper->column('order_details', 'product_number', 'product_number'))->toBeNull();

    // Explicit remap is honoured.
    config()->set('commerce.columns.products.title', 'product_title');
    expect($mapper->column('products', 'title', 'title'))->toBe('product_title');

    // A blank string is treated as unmapped.
    config()->set('commerce.columns.products.subtitle', '');
    expect($mapper->column('products', 'subtitle', 'subtitle'))->toBeNull();
});

it('reads values through the column map with a default fallback', function (): void {
    $mapper = new ModelAttributeMapper;
    $product = Product::query()->create([
        'title' => 'Mapped product',
        'number' => 'P-001',
        'type' => 1,
        'list_price' => 1000,
        'sales_price' => 800,
        'tax' => 1,
    ]);

    expect($mapper->value($product, 'products', 'number', 'fallback'))->toBe('P-001');

    // Unmapped logical column (null in config) returns the default.
    expect($mapper->value($product, 'order_details', 'product_number', 'DEFAULT'))->toBe('DEFAULT');
});

it('maps logical attributes and drops unmapped keys', function (): void {
    $mapper = new ModelAttributeMapper;

    $mapped = $mapper->map('product_user', [
        'product_id' => 'P',
        'product_number' => 'N', // null in config -> dropped
        'user_id' => 5,
    ]);

    expect($mapped)->toBe(['product_id' => 'P', 'user_id' => 5]);
});

it('filters attributes down to columns that exist on the model table', function (): void {
    $mapper = new ModelAttributeMapper;

    $filtered = $mapper->filterForModel(Product::class, [
        'title' => 'Kept',
        'does_not_exist' => 'Dropped',
    ]);

    expect($filtered)->toBe(['title' => 'Kept']);
});
