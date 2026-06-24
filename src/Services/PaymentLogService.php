<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Models\PaymentLog;

class PaymentLogService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $orderNumber,
        array $payload,
        ?string $statusCode = null,
        ?string $statusMessage = null,
    ): Model {
        /** @var class-string<Model> $paymentLogModel */
        $paymentLogModel = $this->paymentLogModel();

        /** @var Model $log */
        $log = $paymentLogModel::query()->updateOrCreate(
            [
                'order_number' => $orderNumber,
                'status_code' => $statusCode,
            ],
            [
                'response' => $payload,
                'status_message' => $statusMessage,
                'updated_at' => now(),
            ],
        );

        return $log;
    }

    /**
     * @return class-string<Model>
     */
    private function paymentLogModel(): string
    {
        $model = config('commerce.models.payment_log', PaymentLog::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : PaymentLog::class;
    }
}
