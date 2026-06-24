<?php

namespace Lalalili\CommerceCore\DTOs;

class CheckoutLineData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int|string $productId,
        public readonly int $quantity = 1,
        public readonly ?string $title = null,
        public readonly ?int $listPrice = null,
        public readonly ?int $salesPrice = null,
        public readonly ?int $productType = null,
        public readonly ?int $companyId = null,
        public readonly array $attributes = [],
    ) {}

    /**
     * @return array{product_id:int|string, qty:int, title?:string, list_price?:int, sales_price?:int, product_type?:int|null, company_id?:int|null}
     */
    public function toOrderItem(): array
    {
        return array_filter([
            'product_id' => $this->productId,
            'qty' => max(1, $this->quantity),
            'title' => $this->title,
            'list_price' => $this->listPrice,
            'sales_price' => $this->salesPrice,
            'product_type' => $this->productType,
            'company_id' => $this->companyId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
