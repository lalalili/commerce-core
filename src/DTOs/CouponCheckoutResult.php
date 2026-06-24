<?php

namespace Lalalili\CommerceCore\DTOs;

class CouponCheckoutResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly bool $successful,
        public readonly string $message = '',
        public readonly array $data = [],
    ) {}

    /**
     * @return array{success: bool, message: string, data: array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'success' => $this->successful,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
