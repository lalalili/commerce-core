<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Throwable;

class CheckoutSnapshotService
{
    /**
     * @var list<string>
     */
    public const DEFAULT_CONDITION_ATTRIBUTE_KEYS = [
        'event_id',
        'event_title',
        'type',
        'sort',
    ];

    /**
     * @param  list<string>  $itemAttributeKeys
     * @param  list<string>  $conditionAttributeKeys
     * @return array{items:list<array<string, mixed>>, conditions:list<array<string, mixed>>, subtotal:int|float|null, total:int|float|null, hash:string}
     */
    public function captureCart(
        mixed $cart,
        array $itemAttributeKeys = [],
        array $conditionAttributeKeys = self::DEFAULT_CONDITION_ATTRIBUTE_KEYS,
    ): array {
        $items = [];
        foreach ($cart->getContent() as $item) {
            $items[] = $this->cartItemSnapshot($item, $itemAttributeKeys, $conditionAttributeKeys);
        }

        $conditions = [];
        foreach ($this->normalizeConditions($cart->getConditions()) as $condition) {
            $conditions[] = $this->conditionSnapshot($condition, $conditionAttributeKeys);
        }

        $payload = [
            'items' => $items,
            'conditions' => $conditions,
            'subtotal' => $this->numericCartValue(fn (): mixed => $cart->getSubTotal(false)),
            'total' => $this->numericCartValue(fn (): mixed => $cart->getTotal(false)),
        ];

        return $payload + [
            'hash' => $this->hashPayload($payload),
        ];
    }

    /**
     * @param  list<string>  $itemAttributeKeys
     * @param  list<string>  $conditionAttributeKeys
     * @return array<string, mixed>
     */
    public function cartItemSnapshot(
        mixed $item,
        array $itemAttributeKeys = [],
        array $conditionAttributeKeys = self::DEFAULT_CONDITION_ATTRIBUTE_KEYS,
    ): array {
        $conditions = [];
        foreach ($this->normalizeConditions(data_get($item, 'conditions')) as $condition) {
            $conditions[] = $this->conditionSnapshot($condition, $conditionAttributeKeys);
        }

        return [
            'id' => data_get($item, 'id'),
            'associated_model' => data_get($item, 'associatedModel'),
            'name' => html_entity_decode((string) data_get($item, 'name', '')),
            'price' => $this->numericValue(data_get($item, 'price')),
            'quantity' => (int) data_get($item, 'quantity', 0),
            'price_with_conditions' => $this->numericCartValue(fn (): mixed => $item->getPriceWithConditions(false)),
            'price_sum_with_conditions' => $this->numericCartValue(fn (): mixed => $item->getPriceSumWithConditions(false)),
            'attributes' => $this->filterArray(data_get($item, 'attributes'), $itemAttributeKeys),
            'conditions' => $conditions,
        ];
    }

    /**
     * @param  list<string>  $attributeKeys
     * @return array{name:mixed, type:mixed, value:mixed, attributes:array<string, mixed>}
     */
    public function conditionSnapshot(
        mixed $condition,
        array $attributeKeys = self::DEFAULT_CONDITION_ATTRIBUTE_KEYS,
    ): array {
        if (! is_object($condition)) {
            return [
                'name' => null,
                'type' => null,
                'value' => null,
                'attributes' => [],
            ];
        }

        $attributes = method_exists($condition, 'getAttributes') ? (array) $condition->getAttributes() : [];

        return [
            'name' => method_exists($condition, 'getName') ? $condition->getName() : null,
            'type' => method_exists($condition, 'getType') ? $condition->getType() : null,
            'value' => method_exists($condition, 'getValue') ? $condition->getValue() : null,
            'attributes' => $this->filterArray($attributes, $attributeKeys),
        ];
    }

    /**
     * @return list<mixed>
     */
    public function normalizeConditions(mixed $conditions): array
    {
        if ($conditions instanceof Collection) {
            return array_values($conditions->values()->all());
        }

        if (is_array($conditions)) {
            return array_values($conditions);
        }

        return $conditions !== null ? [$conditions] : [];
    }

    /**
     * @param  iterable<int, mixed>|mixed  $cartContent
     */
    public function expectedCartLineCount(mixed $cartContent): int
    {
        if (! is_iterable($cartContent)) {
            return 0;
        }

        $count = 0;
        foreach ($cartContent as $item) {
            if ((int) data_get($item, 'quantity', 0) > 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<string>  $attributeKeys
     * @return array<string, list<mixed>>
     */
    public function cartLineConditionAttributes(mixed $item, array $attributeKeys = ['event_id', 'event_title']): array
    {
        $attributes = [];
        foreach ($attributeKeys as $attributeKey) {
            $attributes[$attributeKey] = [];
        }

        foreach ($this->normalizeConditions(data_get($item, 'conditions')) as $condition) {
            if (! is_object($condition) || ! method_exists($condition, 'getAttributes')) {
                continue;
            }

            $conditionAttributes = (array) $condition->getAttributes();
            foreach ($attributeKeys as $attributeKey) {
                $attributes[$attributeKey][] = data_get($conditionAttributes, $attributeKey);
            }
        }

        return $attributes;
    }

    public function cartItemPriceWithConditions(mixed $item): int|float|null
    {
        if (! is_object($item) || ! method_exists($item, 'getPriceWithConditions')) {
            return null;
        }

        return $this->numericCartValue(fn (): mixed => $item->getPriceWithConditions(false));
    }

    public function moneyString(mixed $value): string
    {
        return number_format((float) ($this->numericValue($value) ?? 0), 0, '.', '');
    }

    public function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @param  list<array<string, mixed>>  $lineSnapshots
     * @param  array<string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}>  $schema
     * @return list<array<string, mixed>>
     */
    public function normalizeLineSnapshots(array $lineSnapshots, array $schema): array
    {
        return array_values(collect($lineSnapshots)
            ->map(fn (array $lineSnapshot): array => $this->normalizeLineSnapshot($lineSnapshot, $schema))
            ->values()
            ->all());
    }

    /**
     * @param  list<array<string, mixed>>  $lineSnapshots
     * @param  array<string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}>  $schema
     * @param  array<string, mixed>  $base
     * @return list<array<string, mixed>>
     */
    public function orderDetailRows(
        array $lineSnapshots,
        array $schema,
        array $base = [],
        string $productKey = 'product_id',
        string $quantityKey = 'quantity',
    ): array {
        $rows = [];

        foreach ($lineSnapshots as $lineSnapshot) {
            if ((string) ($lineSnapshot[$productKey] ?? '') === '' || (int) ($lineSnapshot[$quantityKey] ?? 0) < 1) {
                continue;
            }

            $rows[] = array_merge($base, $this->normalizeLineSnapshot($lineSnapshot, $schema));
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $lineSnapshots
     */
    public function detailTotal(array $lineSnapshots, string $salesPriceKey = 'sales_price', string $quantityKey = 'quantity'): int
    {
        return (int) collect($lineSnapshots)
            ->sum(fn (array $lineSnapshot): int => (int) ($lineSnapshot[$salesPriceKey] ?? 0) * (int) ($lineSnapshot[$quantityKey] ?? 0));
    }

    /**
     * @param  list<array<string, mixed>>  $lineSnapshots
     * @param  array<string, mixed>  $cartSnapshot
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function checkoutSnapshotAttributes(
        int $orderId,
        ?int $userId,
        array $lineSnapshots,
        array $cartSnapshot,
        array $payload,
        CarbonInterface $capturedAt,
        int $snapshotVersion = 1,
        ?int $detailTotal = null,
        array $extra = [],
    ): array {
        return array_merge([
            'order_id' => $orderId,
            'user_id' => $userId,
            'snapshot_version' => $snapshotVersion,
            'line_count' => count($lineSnapshots),
            'cart_total' => $cartSnapshot['total'] ?? null,
            'detail_total' => $detailTotal ?? $this->detailTotal($lineSnapshots),
            'payload' => $payload,
            'cart_hash' => $cartSnapshot['hash'] ?? null,
            'payload_hash' => $this->hashPayload($payload),
            'captured_at' => $capturedAt,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $orderSnapshot
     * @param  array<string, mixed>  $requestSnapshot
     * @param  array<string, mixed>  $cartSnapshot
     * @param  list<array<string, mixed>>  $lineSnapshots
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function checkoutSuccessPayload(
        array $orderSnapshot,
        array $requestSnapshot,
        array $cartSnapshot,
        array $lineSnapshots,
        int $detailTotal,
        CarbonInterface $capturedAt,
        int $snapshotVersion = 1,
        array $extra = [],
    ): array {
        return array_merge([
            'snapshot_version' => $snapshotVersion,
            'captured_at' => $capturedAt->toDateTimeString(),
            'order' => $orderSnapshot,
            'request' => $requestSnapshot,
            'checkout_cart' => $cartSnapshot,
            'line_snapshots' => $lineSnapshots,
            'detail_total' => $detailTotal,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $requestSnapshot
     * @param  array<string, mixed>  $cartContext
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function checkoutFailureContext(
        Throwable $exception,
        array $requestSnapshot,
        array $cartContext,
        ?CarbonInterface $capturedAt = null,
        int $snapshotVersion = 1,
        array $extra = [],
    ): array {
        return array_merge([
            'snapshot_version' => $snapshotVersion,
            'captured_at' => ($capturedAt ?? now())->toDateTimeString(),
            'exception' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'request' => $requestSnapshot,
            'checkout_cart' => $cartContext,
        ], $extra);
    }

    public function requestInput(mixed $request, string $key, mixed $default = null): mixed
    {
        if (! is_object($request) || ! method_exists($request, 'input')) {
            return $default;
        }

        try {
            return $request->input($key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    public function requestFilled(mixed $request, string $key, bool $default = false): bool
    {
        if (! is_object($request) || ! method_exists($request, 'filled')) {
            return $default;
        }

        try {
            return (bool) $request->filled($key);
        } catch (Throwable) {
            return $default;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function hashPayload(array $payload): string
    {
        $normalized = $this->sortRecursive($payload);

        return hash('sha256', json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function numericCartValue(callable $callback): int|float|null
    {
        try {
            return $this->numericValue($callback());
        } catch (Throwable) {
            return null;
        }
    }

    public function numericValue(mixed $value): int|float|null
    {
        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return floor($float) === $float ? (int) $float : $float;
    }

    /**
     * @param  array<string, mixed>  $lineSnapshot
     * @param  array<string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}>  $schema
     * @return array<string, mixed>
     */
    private function normalizeLineSnapshot(array $lineSnapshot, array $schema): array
    {
        $normalized = [];

        foreach ($schema as $target => $definition) {
            $source = $definition['source'] ?? $target;
            $default = $definition['default'] ?? null;

            $normalized[$target] = $this->normalizeSchemaValue(
                $lineSnapshot[$source] ?? $default,
                $definition['type'] ?? 'raw',
                $default,
            );
        }

        return $normalized;
    }

    private function normalizeSchemaValue(mixed $value, string $type, mixed $default): mixed
    {
        if ($type === 'datetime') {
            return $value instanceof CarbonInterface ? $value->toDateTimeString() : $default;
        }

        return match ($type) {
            'string' => (string) ($value ?? $default ?? ''),
            'int' => (int) ($value ?? $default ?? 0),
            'float' => (float) ($value ?? $default ?? 0),
            'bool' => (bool) ($value ?? $default ?? false),
            'array' => is_array($value) ? $value : (is_array($default) ? $default : []),
            'json' => $this->jsonValue($value, $default),
            default => $value,
        };
    }

    private function jsonValue(mixed $value, mixed $default): string
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        $payload = is_array($value) ? $value : (is_array($default) ? $default : []);

        try {
            return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return '[]';
        }
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function filterArray(mixed $value, array $keys): array
    {
        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (! is_array($value)) {
            return [];
        }

        if ($keys === []) {
            return [];
        }

        return array_intersect_key($value, array_flip($keys));
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $child) {
            $value[$key] = $this->sortRecursive($child);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
