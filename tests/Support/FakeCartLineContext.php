<?php

namespace Lalalili\CommerceCore\Tests\Support;

class FakeCartLineContext
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public int|string $id,
        public int|string $productId,
        public int $quantity,
        public float $unitPrice,
        public string $associatedModel,
        public array $attributes,
    ) {}
}
