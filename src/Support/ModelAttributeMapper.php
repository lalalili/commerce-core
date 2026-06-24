<?php

namespace Lalalili\CommerceCore\Support;

use Illuminate\Database\Eloquent\Model;

class ModelAttributeMapper
{
    /**
     * @var array<class-string<Model>, array<string, bool>>
     */
    private array $columnExists = [];

    public function column(string $tableKey, string $logicalColumn, ?string $default = null): ?string
    {
        $configured = config("commerce.columns.{$tableKey}.{$logicalColumn}", $default);

        return is_string($configured) && $configured !== '' ? $configured : null;
    }

    public function value(Model $model, string $tableKey, string $logicalColumn, mixed $default = null): mixed
    {
        $column = $this->column($tableKey, $logicalColumn, $logicalColumn);

        if ($column === null) {
            return $default;
        }

        return data_get($model, $column, $default);
    }

    /**
     * @param  array<string, mixed>  $logicalAttributes
     * @return array<string, mixed>
     */
    public function map(string $tableKey, array $logicalAttributes): array
    {
        $mapped = [];

        foreach ($logicalAttributes as $logicalColumn => $value) {
            $column = $this->column($tableKey, (string) $logicalColumn, (string) $logicalColumn);

            if ($column === null) {
                continue;
            }

            $mapped[$column] = $value;
        }

        return $mapped;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function filterForModel(string $modelClass, array $attributes): array
    {
        /** @var Model $model */
        $model = new $modelClass;

        return collect($attributes)
            ->filter(fn (mixed $value, string $column): bool => $this->hasColumn($model, $column))
            ->all();
    }

    private function hasColumn(Model $model, string $column): bool
    {
        $modelClass = $model::class;

        if (array_key_exists($column, $this->columnExists[$modelClass] ?? [])) {
            return $this->columnExists[$modelClass][$column];
        }

        return $this->columnExists[$modelClass][$column] = $model
            ->getConnection()
            ->getSchemaBuilder()
            ->hasColumn($model->getTable(), $column);
    }
}
