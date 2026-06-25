<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Support\QueryBuilderHelper;

it('inserts rows through a query builder and ignores duplicate keys', function (): void {
    $id = (string) Str::ulid();

    QueryBuilderHelper::insertOrIgnore(DB::table((new Product)->getTable()), [
        [
            'id' => $id,
            'number' => 'QB-001',
            'title' => 'Query Builder Product',
            'type' => 1,
            'list_price' => 1000,
            'sales_price' => 800,
            'tax' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $id,
            'number' => 'QB-002',
            'title' => 'Duplicate Query Builder Product',
            'type' => 1,
            'list_price' => 1000,
            'sales_price' => 800,
            'tax' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(Product::query()->whereKey($id)->count())->toBe(1)
        ->and(Product::query()->whereKey($id)->value('number'))->toBe('QB-001');
});

it('inserts rows through an eloquent builder and ignores duplicate keys', function (): void {
    $id = (string) Str::ulid();

    QueryBuilderHelper::insertOrIgnore(Product::query(), [
        [
            'id' => $id,
            'number' => 'EL-001',
            'title' => 'Eloquent Product',
            'type' => 1,
            'list_price' => 1000,
            'sales_price' => 800,
            'tax' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => $id,
            'number' => 'EL-002',
            'title' => 'Duplicate Eloquent Product',
            'type' => 1,
            'list_price' => 1000,
            'sales_price' => 800,
            'tax' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(Product::query()->whereKey($id)->count())->toBe(1)
        ->and(Product::query()->whereKey($id)->value('number'))->toBe('EL-001');
});
