<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;

class EntitlementService
{
    public function grantOrder(Order $order, ?int $createdBy = null): void
    {
        $order->loadMissing(['details.product']);
        /** @var class-string<Model> $productUserModel */
        $productUserModel = config('commerce.models.product_user');
        $now = now();

        foreach ($order->details as $detail) {
            if (! $detail instanceof OrderDetail || ! $detail->product instanceof Model) {
                continue;
            }

            $productUserModel::query()->firstOrCreate(
                [
                    'product_id' => $detail->product->getKey(),
                    'user_id'    => $order->user_id,
                ],
                [
                    'order_number' => $order->number,
                    'product_type' => $detail->product_type,
                    'created_by'   => $createdBy ?? $order->user_id,
                    'created_at'   => $now,
                ],
            );
        }
    }

    public function revokeOrder(Order $order): void
    {
        /** @var class-string<Model> $productUserModel */
        $productUserModel = config('commerce.models.product_user');
        $productIds = $order->details()
            ->pluck('product_id')
            ->filter()
            ->values()
            ->all();

        if ($productIds === []) {
            return;
        }

        $productUserModel::query()
            ->whereIn('product_id', $productIds)
            ->where('user_id', $order->user_id)
            ->delete();
    }
}
