<?php

namespace Lalalili\CommerceCore\Services;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Models\ProductUser;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;

class EntitlementService
{
    public function __construct(private readonly ModelAttributeMapper $attributes) {}

    public function grantOrder(Model $order, int|string|null $createdBy = null, ?DateTimeInterface $createdAt = null): void
    {
        if (! $this->enabled()) {
            return;
        }

        $detailsRelation = $this->detailsRelation();

        $order->loadMissing(["{$detailsRelation}.product"]);
        $productUserModel = $this->productUserModel();
        $grantedAt = $createdAt ?? now();

        foreach ($order->getRelationValue($detailsRelation) ?? [] as $detail) {
            if (! $detail instanceof Model || ! $detail->getRelationValue('product') instanceof Model) {
                continue;
            }

            /** @var Model $product */
            $product = $detail->getRelationValue('product');
            $lookup = $this->attributes->filterForModel($productUserModel, $this->attributes->map('product_user', [
                'product_id' => $product->getKey(),
                'product_number' => $this->attributes->value($product, 'products', 'number', $product->getKey()),
                'user_id' => data_get($order, 'user_id'),
            ]));
            $values = $this->attributes->filterForModel($productUserModel, $this->attributes->map('product_user', [
                'order_number' => data_get($order, 'number'),
                'product_type' => data_get($detail, $this->attributes->column('order_details', 'product_type', 'product_type') ?? 'product_type'),
                'created_by' => $createdBy ?? data_get($order, 'user_id'),
                'created_at' => $grantedAt,
            ]));

            if ($lookup === []) {
                continue;
            }

            $productUserModel::query()->firstOrCreate(
                $lookup,
                $values,
            );
        }
    }

    public function revokeOrder(Model $order): void
    {
        if (! $this->enabled()) {
            return;
        }

        $productUserModel = $this->productUserModel();
        $productUserKeyColumn = $this->attributes->column('product_user', 'product_id', 'product_id')
            ?? $this->attributes->column('product_user', 'product_number', 'product_number');

        if ($productUserKeyColumn === null) {
            return;
        }

        $detailsRelation = $this->detailsRelation();
        $order->loadMissing(["{$detailsRelation}.product"]);

        $details = $order->getRelationValue($detailsRelation);

        if (! is_iterable($details)) {
            return;
        }

        $productIds = collect($details)
            ->filter(fn (mixed $detail): bool => $detail instanceof Model)
            ->map(function (Model $detail) use ($productUserKeyColumn): mixed {
                $product = $detail->getRelationValue('product');

                if (! $product instanceof Model) {
                    return null;
                }

                if ($productUserKeyColumn === $this->attributes->column('product_user', 'product_id', 'product_id')) {
                    return $product->getKey();
                }

                return $this->attributes->value($product, 'products', 'number', $product->getKey());
            })
            ->filter()
            ->values()
            ->all();

        if ($productIds === []) {
            return;
        }

        $productUserModel::query()
            ->whereIn($productUserKeyColumn, $productIds)
            ->where($this->attributes->column('product_user', 'user_id', 'user_id') ?? 'user_id', data_get($order, 'user_id'))
            ->delete();
    }

    /**
     * @return class-string<Model>
     */
    private function productUserModel(): string
    {
        $model = config('commerce.models.product_user', ProductUser::class);

        return is_string($model) && is_a($model, Model::class, true) ? $model : ProductUser::class;
    }

    private function detailsRelation(): string
    {
        $relation = config('commerce.relationships.order_details', 'details');

        return is_string($relation) && $relation !== '' ? $relation : 'details';
    }

    private function enabled(): bool
    {
        return (bool) config('commerce.entitlements.enabled', true);
    }
}
