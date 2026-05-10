<?php

namespace Lalalili\CommerceCore\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasUlids;

    protected $guarded = [];

    public function getTable(): string
    {
        return config('commerce.tables.products', 'products');
    }

    /**
     * @return HasOne<ProductDetail, $this>
     */
    public function detail(): HasOne
    {
        /** @var HasOne<ProductDetail, $this> $relation */
        $relation = $this->hasOne(config('commerce.models.product_detail', ProductDetail::class));

        return $relation;
    }

    /**
     * @return BelongsToMany<Model, $this>
     */
    public function users(): BelongsToMany
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');

        /** @var BelongsToMany<Model, $this> $relation */
        $relation = $this->belongsToMany($userModel, config('commerce.tables.product_user', 'product_user'));

        return $relation;
    }
}
