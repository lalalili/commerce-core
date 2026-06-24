<?php

namespace Lalalili\CommerceCore\DTOs;

use Carbon\CarbonInterface;
use Lalalili\CommerceCore\Enums\PaymentApplicationOutcome;

class PaymentApplicationData
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $orderNumber,
        public readonly PaymentApplicationOutcome $outcome,
        public readonly array $payload,
        public readonly ?string $statusCode = null,
        public readonly ?string $statusMessage = null,
        public readonly ?int $amount = null,
        public readonly ?CarbonInterface $paidAt = null,
        public readonly ?string $outcomeMessage = null,
        public readonly string $gatewayLabel = '',
    ) {}

    public function orderMessage(): string
    {
        return $this->outcomeMessage ?? $this->statusMessage ?? '';
    }
}
