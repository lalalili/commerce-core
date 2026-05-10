<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;

class EntitlementService
{
    public function grantOrder(Model $order, ?int $createdBy = null): void
    {
        $detailsRelation = $this->detailsRelation();

        $order->loadMissing(["{$detailsRelation}.product"]);
        /** @var class-string<Model> $productUserModel */
        $productUserModel = config('commerce.models.product_user');
        $now = now();

        foreach ($order->getRelationValue($detailsRelation) ?? [] as $detail) {
            if (! $detail instanceof Model || ! $detail->getRelationValue('product') instanceof Model) {
                continue;
            }

            /** @var Model $product */
            $product = $detail->getRelationValue('product');
            $productUserModel::query()->firstOrCreate(
                [
                    'product_id' => $product->getKey(),
                    'user_id' => $order->user_id,
                ],
                [
                    'order_number' => $order->number,
                    'product_type' => $detail->product_type,
                    'created_by' => $createdBy ?? $order->user_id,
                    'created_at' => $now,
                ],
            );
        }
    }

    public function revokeOrder(Model $order): void
    {
        /** @var class-string<Model> $productUserModel */
        $productUserModel = config('commerce.models.product_user');
        $productIds = $order->{$this->detailsRelation()}()
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

    private function detailsRelation(): string
    {
        $relation = config('commerce.relationships.order_details', 'details');

        return is_string($relation) && $relation !== '' ? $relation : 'details';
    }
}
