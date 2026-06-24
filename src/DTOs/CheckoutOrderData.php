<?php

namespace Lalalili\CommerceCore\DTOs;

class CheckoutOrderData
{
    /**
     * @param  list<CheckoutLineData>  $lines
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $lines,
        public readonly array $attributes = [],
    ) {}

    /**
     * @return list<array{product_id:int|string, qty:int, title?:string, list_price?:int, sales_price?:int, product_type?:int|null, company_id?:int|null}>
     */
    public function orderItems(): array
    {
        return array_map(
            static fn (CheckoutLineData $line): array => $line->toOrderItem(),
            $this->lines,
        );
    }
}
