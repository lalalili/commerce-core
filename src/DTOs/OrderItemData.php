<?php

namespace Lalalili\CommerceCore\DTOs;

use Illuminate\Database\Eloquent\Model;

class OrderItemData
{
    public function __construct(
        public readonly Model $product,
        public readonly int $quantity,
        public readonly string $title,
        public readonly int $listPrice,
        public readonly int $salesPrice,
        public readonly mixed $productType,
        public readonly mixed $companyId,
    ) {}

    public function totalListPrice(): int
    {
        return $this->listPrice * $this->quantity;
    }

    public function totalSalesPrice(): int
    {
        return $this->salesPrice * $this->quantity;
    }
}
