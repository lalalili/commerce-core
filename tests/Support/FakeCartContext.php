<?php

namespace Lalalili\CommerceCore\Tests\Support;

class FakeCartContext
{
    public function __construct(
        public float $orderTotal,
        public float $allAmount,
        public float $bookAmount,
        public float $ebookAmount,
        public float $specificProductsAmount,
        public bool $hasBook,
        public bool $hasEbook,
        public bool $hasSpecificProducts,
    ) {}
}
