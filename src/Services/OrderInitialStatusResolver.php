<?php

namespace Lalalili\CommerceCore\Services;

class OrderInitialStatusResolver
{
    /**
     * @param  list<mixed>|null  $pendingPaymentTypes
     */
    public function resolve(
        mixed $paymentType,
        mixed $pendingStatus,
        mixed $readyStatus,
        ?array $pendingPaymentTypes = null,
    ): mixed {
        $pendingPaymentTypes ??= $this->configuredPendingPaymentTypes();

        return in_array($paymentType, $pendingPaymentTypes, true)
            ? $pendingStatus
            : $readyStatus;
    }

    /**
     * @return list<mixed>
     */
    private function configuredPendingPaymentTypes(): array
    {
        $paymentTypes = config('commerce.checkout.pending_payment_types', []);

        return is_array($paymentTypes) ? array_values($paymentTypes) : [];
    }
}
