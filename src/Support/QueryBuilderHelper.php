<?php

namespace Lalalili\CommerceCore\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class QueryBuilderHelper
{
    /**
     * @template TModel of Model
     *
     * @param  QueryBuilder|EloquentBuilder<TModel>  $builder
     * @param  array<int, array<string, mixed>>  $values
     */
    public static function insertOrIgnore(QueryBuilder|EloquentBuilder $builder, array $values): int
    {
        return $builder->insertOrIgnore($values);
    }
}
