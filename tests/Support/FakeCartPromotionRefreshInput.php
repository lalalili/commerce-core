<?php

namespace Lalalili\CommerceCore\Tests\Support;

class FakeCartPromotionRefreshInput
{
    /**
     * @param  list<object>  $lines
     * @param  array<int|string, object>  $promotionSetsByProductId
     */
    public function __construct(
        public object $cartContext,
        public array $lines,
        public array $promotionSetsByProductId,
        public string $giftFulfillment,
    ) {}
}
