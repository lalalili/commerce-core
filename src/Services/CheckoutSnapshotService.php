<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
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

    public const CHECKOUT_FAILURE_CART_ITEMS = 'cart_items';

    public const CHECKOUT_FAILURE_COUPON_CONDITION_MISMATCH = 'coupon_condition_mismatch';

    public const CHECKOUT_FAILURE_LINE_SNAPSHOTS = 'line_snapshots';

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
     * @param  array<int, array<string, mixed>>  $lineSnapshots
     * @param  iterable<int, mixed>|mixed  $cartContent
     */
    public function hasCompleteLineSnapshots(array $lineSnapshots, mixed $cartContent): bool
    {
        return $lineSnapshots !== []
            && count($lineSnapshots) === $this->expectedCartLineCount($cartContent);
    }

    /**
     * @param  array{has_positive_total?: bool, has_cart_items?: bool, coupon_condition_mismatch?: bool}  $checkoutConsistency
     */
    public function checkoutConsistencyFailure(array $checkoutConsistency): ?string
    {
        if (! ($checkoutConsistency['has_positive_total'] ?? false) || ! ($checkoutConsistency['has_cart_items'] ?? false)) {
            return self::CHECKOUT_FAILURE_CART_ITEMS;
        }

        if ($checkoutConsistency['coupon_condition_mismatch'] ?? false) {
            return self::CHECKOUT_FAILURE_COUPON_CONDITION_MISMATCH;
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineSnapshots
     * @param  iterable<int, mixed>|mixed  $cartContent
     */
    public function cartLineSnapshotFailure(array $lineSnapshots, mixed $cartContent): ?string
    {
        if (! $this->hasCompleteLineSnapshots($lineSnapshots, $cartContent)) {
            return self::CHECKOUT_FAILURE_LINE_SNAPSHOTS;
        }

        return null;
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

    /**
     * @param  list<string>  $conditionAttributeKeys
     * @return array{
     *     id:string,
     *     title:string,
     *     decoded_title:string,
     *     quantity:int,
     *     list_price:string,
     *     sales_price:string,
     *     condition_attributes:array<string, list<mixed>>
     * }
     */
    public function cartLineBaseValues(
        mixed $item,
        array $conditionAttributeKeys = ['event_id', 'event_title'],
    ): array {
        $title = (string) data_get($item, 'name', '');

        return [
            'id' => (string) data_get($item, 'id', ''),
            'title' => $title,
            'decoded_title' => html_entity_decode($title),
            'quantity' => (int) data_get($item, 'quantity', 0),
            'list_price' => $this->moneyString(data_get($item, 'price', 0)),
            'sales_price' => $this->moneyString($this->cartItemPriceWithConditions($item)),
            'condition_attributes' => $this->cartLineConditionAttributes($item, $conditionAttributeKeys),
        ];
    }

    /**
     * @template TCartLineSnapshot of array<string, mixed>
     *
     * @param  iterable<int, mixed>|mixed  $cartContent
     * @param  callable(mixed, array{id:string,title:string,decoded_title:string,quantity:int,list_price:string,sales_price:string,condition_attributes:array<string, list<mixed>>}, self=): (TCartLineSnapshot|null)  $mapper
     * @param  list<string>  $conditionAttributeKeys
     * @return list<TCartLineSnapshot>
     */
    public function cartLineSnapshots(
        mixed $cartContent,
        callable $mapper,
        array $conditionAttributeKeys = ['event_id', 'event_title'],
    ): array {
        if (! is_iterable($cartContent)) {
            return [];
        }

        $lineSnapshots = [];

        foreach ($cartContent as $item) {
            $snapshot = $mapper(
                $item,
                $this->cartLineBaseValues($item, $conditionAttributeKeys),
                $this,
            );

            if (is_array($snapshot)) {
                $lineSnapshots[] = $snapshot;
            }
        }

        return $lineSnapshots;
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

    /**
     * @return array{total_sales_price:int|string,total_discount_amt:int|string}
     */
    public function checkoutTotals(mixed $cart, bool $stringValues = false): array
    {
        $totalSalesPrice = $this->numericCartValue(fn (): mixed => $cart->getTotal(false)) ?? 0;
        $totalDiscountAmount = ($this->numericCartValue(fn (): mixed => $cart->getSubTotal(false)) ?? 0) - $totalSalesPrice;

        if ($stringValues) {
            return [
                'total_sales_price' => $this->moneyString($totalSalesPrice),
                'total_discount_amt' => $this->moneyString($totalDiscountAmount),
            ];
        }

        return [
            'total_sales_price' => (int) round((float) $totalSalesPrice),
            'total_discount_amt' => (int) round((float) $totalDiscountAmount),
        ];
    }

    /**
     * @return array{
     *     totals: array{total_sales_price:int|string,total_discount_amt:int|string},
     *     has_cart_items: bool,
     *     has_positive_total: bool,
     *     coupon_condition_mismatch: bool,
     *     is_ready: bool
     * }
     */
    public function checkoutConsistencySummary(
        mixed $cart,
        mixed $cartContent,
        bool $hasMemberCouponCode,
        bool $hasPromotionCouponCode,
        bool $stringValues = false,
        string $memberCouponConditionType = 'member_coupon',
        string $promotionCouponConditionType = 'promotion_coupon',
    ): array {
        $totals = $this->checkoutTotals($cart, $stringValues);
        $totalSalesPrice = $this->numericValue($totals['total_sales_price']) ?? 0;
        $hasCartItems = $this->expectedCartLineCount($cartContent) > 0;
        $couponConditionMismatch = $this->couponConditionMismatch(
            hasMemberCouponCode: $hasMemberCouponCode,
            memberCouponConditions: $this->cartConditionsByType($cart, $memberCouponConditionType),
            hasPromotionCouponCode: $hasPromotionCouponCode,
            promotionCouponConditions: $this->cartConditionsByType($cart, $promotionCouponConditionType),
        );

        return [
            'totals' => $totals,
            'has_cart_items' => $hasCartItems,
            'has_positive_total' => (float) $totalSalesPrice >= 1,
            'coupon_condition_mismatch' => $couponConditionMismatch,
            'is_ready' => $hasCartItems && (float) $totalSalesPrice >= 1 && ! $couponConditionMismatch,
        ];
    }

    public function cartConditionsByType(mixed $cart, string $type): mixed
    {
        if (! is_object($cart) || ! method_exists($cart, 'getConditionsByType')) {
            return [];
        }

        try {
            return $cart->getConditionsByType($type);
        } catch (Throwable) {
            return [];
        }
    }

    public function couponConditionMismatch(
        bool $hasMemberCouponCode,
        mixed $memberCouponConditions,
        bool $hasPromotionCouponCode,
        mixed $promotionCouponConditions,
    ): bool {
        $hasMemberCouponConditions = $this->normalizeConditions($memberCouponConditions) !== [];
        $hasPromotionCouponConditions = $this->normalizeConditions($promotionCouponConditions) !== [];

        return ($hasMemberCouponCode && ! $hasMemberCouponConditions)
            || ($hasPromotionCouponCode && ! $hasPromotionCouponConditions)
            || (! $hasMemberCouponCode && $hasMemberCouponConditions)
            || (! $hasPromotionCouponCode && $hasPromotionCouponConditions);
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
     * @param  array<string, mixed>  $snapshot
     * @param  array<int|string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}|string>  $schema
     * @return array<string, mixed>
     */
    public function normalizeSnapshot(array $snapshot, array $schema): array
    {
        return $this->normalizeLineSnapshot($snapshot, $this->normalizeSnapshotSchema($schema));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineSnapshots
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
     * @return array{has_preorder: bool, preorder_hold_until: CarbonInterface|null}
     */
    public function preorderSummary(
        array $lineSnapshots,
        string $isPreorderKey = 'is_preorder',
        string $releaseAtKey = 'preorder_release_at',
    ): array {
        $hasPreorder = false;
        $holdUntil = null;

        foreach ($lineSnapshots as $lineSnapshot) {
            if (! ($lineSnapshot[$isPreorderKey] ?? false)) {
                continue;
            }

            $hasPreorder = true;

            $releaseAt = $lineSnapshot[$releaseAtKey] ?? null;
            if (! $releaseAt instanceof CarbonInterface) {
                continue;
            }

            if (! $holdUntil instanceof CarbonInterface || $releaseAt->gt($holdUntil)) {
                $holdUntil = $releaseAt;
            }
        }

        return [
            'has_preorder' => $hasPreorder,
            'preorder_hold_until' => $holdUntil,
        ];
    }

    public function conditionDiscountNotes(
        mixed $conditions,
        string $prefix = '活動類折扣:',
        string $amountPrefix = '-$',
        string $separator = ',',
    ): string {
        $notes = '';

        foreach ($this->normalizeConditions($conditions) as $condition) {
            if (! is_object($condition) || ! method_exists($condition, 'getName') || ! method_exists($condition, 'getValue')) {
                continue;
            }

            $notes .= $prefix.$condition->getName().$amountPrefix.((float) $condition->getValue() * -1).$separator;
        }

        return $notes;
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
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $lineSnapshots
     * @param  array<int|string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}|string>  $orderSchema
     * @param  array<int|string, array{source?:string, filled?:bool, default?:mixed}|string>  $requestSchema
     * @param  array<string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}>  $lineSnapshotSchema
     * @param  array{
     *     cart_item_attribute_keys?: list<string>,
     *     condition_attribute_keys?: list<string>,
     *     order_request_schema?: array<int|string, array{source?:string, filled?:bool, default?:mixed}|string>,
     *     attribute_extra?: array<string, mixed>,
     *     payload_extra?: array<string, mixed>,
     *     snapshot_version?: int,
     *     detail_total?: int,
     *     order_number_key?: string,
     *     order_number?: string
     * }  $options
     * @return array{
     *     lookup: array{order_number: string},
     *     attributes: array<string, mixed>,
     *     payload: array<string, mixed>,
     *     cart_snapshot: array<string, mixed>,
     *     line_snapshots: list<array<string, mixed>>,
     *     detail_total: int
     * }
     */
    public function checkoutSuccessSnapshotRecord(
        mixed $request,
        mixed $cart,
        array $order,
        int $orderId,
        ?int $userId,
        array $lineSnapshots,
        CarbonInterface $capturedAt,
        array $orderSchema,
        array $requestSchema,
        array $lineSnapshotSchema,
        array $options = [],
    ): array {
        $lineSnapshots = array_values($lineSnapshots);
        $cartSnapshot = $this->captureCart(
            $cart,
            $options['cart_item_attribute_keys'] ?? [],
            $options['condition_attribute_keys'] ?? self::DEFAULT_CONDITION_ATTRIBUTE_KEYS,
        );
        $normalizedLineSnapshots = $this->normalizeLineSnapshots($lineSnapshots, $lineSnapshotSchema);
        $detailTotal = $options['detail_total'] ?? $this->detailTotal($lineSnapshots);
        $snapshotVersion = $options['snapshot_version'] ?? 1;
        $orderNumberKey = $options['order_number_key'] ?? 'number';
        $orderNumber = (string) ($options['order_number'] ?? $order[$orderNumberKey] ?? '');

        $orderSnapshot = array_merge([
            'id' => $orderId,
            'user_id' => $userId,
        ], $this->normalizeSnapshot($order, $orderSchema));

        if (isset($options['order_request_schema'])) {
            $orderSnapshot = array_merge($orderSnapshot, $this->requestSnapshot($request, $options['order_request_schema']));
        }

        $payload = $this->checkoutSuccessPayload(
            orderSnapshot: $orderSnapshot,
            requestSnapshot: $this->requestSnapshot($request, $requestSchema),
            cartSnapshot: $cartSnapshot,
            lineSnapshots: $normalizedLineSnapshots,
            detailTotal: $detailTotal,
            capturedAt: $capturedAt,
            snapshotVersion: $snapshotVersion,
            extra: $options['payload_extra'] ?? [],
        );

        return [
            'lookup' => [
                'order_number' => $orderNumber,
            ],
            'attributes' => $this->checkoutSnapshotAttributes(
                orderId: $orderId,
                userId: $userId,
                lineSnapshots: $lineSnapshots,
                cartSnapshot: $cartSnapshot,
                payload: $payload,
                capturedAt: $capturedAt,
                snapshotVersion: $snapshotVersion,
                detailTotal: $detailTotal,
                extra: $options['attribute_extra'] ?? [],
            ),
            'payload' => $payload,
            'cart_snapshot' => $cartSnapshot,
            'line_snapshots' => $normalizedLineSnapshots,
            'detail_total' => $detailTotal,
        ];
    }

    /**
     * @param  class-string<Model>  $snapshotModel
     * @param  array<string, mixed>  $order
     * @param  array<int, array<string, mixed>>  $lineSnapshots
     * @param  array<int|string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}|string>  $orderSchema
     * @param  array<int|string, array{source?:string, filled?:bool, default?:mixed}|string>  $requestSchema
     * @param  array<string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}>  $lineSnapshotSchema
     * @param  array{
     *     cart_item_attribute_keys?: list<string>,
     *     condition_attribute_keys?: list<string>,
     *     order_request_schema?: array<int|string, array{source?:string, filled?:bool, default?:mixed}|string>,
     *     attribute_extra?: array<string, mixed>,
     *     payload_extra?: array<string, mixed>,
     *     snapshot_version?: int,
     *     detail_total?: int,
     *     order_number_key?: string,
     *     order_number?: string
     * }  $options
     * @return array{
     *     lookup: array{order_number: string},
     *     attributes: array<string, mixed>,
     *     payload: array<string, mixed>,
     *     cart_snapshot: array<string, mixed>,
     *     line_snapshots: list<array<string, mixed>>,
     *     detail_total: int
     * }
     */
    public function persistCheckoutSuccessSnapshotRecord(
        string $snapshotModel,
        mixed $request,
        mixed $cart,
        array $order,
        int $orderId,
        ?int $userId,
        array $lineSnapshots,
        CarbonInterface $capturedAt,
        array $orderSchema,
        array $requestSchema,
        array $lineSnapshotSchema,
        array $options = [],
    ): array {
        $this->assertSnapshotModel($snapshotModel);

        $record = $this->checkoutSuccessSnapshotRecord(
            request: $request,
            cart: $cart,
            order: $order,
            orderId: $orderId,
            userId: $userId,
            lineSnapshots: $lineSnapshots,
            capturedAt: $capturedAt,
            orderSchema: $orderSchema,
            requestSchema: $requestSchema,
            lineSnapshotSchema: $lineSnapshotSchema,
            options: $options,
        );

        $snapshotModel::query()->updateOrCreate(
            $record['lookup'],
            $record['attributes'],
        );

        return $record;
    }

    /**
     * @param  class-string<Model>  $snapshotModel
     */
    public function staleCheckoutSnapshotCount(
        string $snapshotModel,
        CarbonInterface $cutoff,
        string $capturedAtColumn = 'captured_at',
    ): int {
        $this->assertSnapshotModel($snapshotModel);

        return (int) $snapshotModel::query()
            ->where($capturedAtColumn, '<', $cutoff)
            ->count();
    }

    /**
     * @param  class-string<Model>  $snapshotModel
     * @return array{
     *     candidate_count: int,
     *     deleted_count: int,
     *     stalled: bool,
     *     stalled_candidate_ids: list<mixed>,
     *     stalled_candidate_id_count: int
     * }
     */
    public function pruneCheckoutSnapshotRecords(
        string $snapshotModel,
        CarbonInterface $cutoff,
        int $batch = 1000,
        string $capturedAtColumn = 'captured_at',
        string $keyColumn = 'id',
    ): array {
        $this->assertSnapshotModel($snapshotModel);

        if ($batch < 1) {
            throw new InvalidArgumentException('Snapshot prune batch must be greater than or equal to 1.');
        }

        $candidateCount = $this->staleCheckoutSnapshotCount($snapshotModel, $cutoff, $capturedAtColumn);
        $deletedCount = 0;

        while (true) {
            $ids = $snapshotModel::query()
                ->where($capturedAtColumn, '<', $cutoff)
                ->orderBy($capturedAtColumn)
                ->limit($batch)
                ->pluck($keyColumn);

            if ($ids->isEmpty()) {
                break;
            }

            $deleteBatchIds = $ids->values()->all();
            $deletedInBatch = $snapshotModel::query()
                ->whereIn($keyColumn, $deleteBatchIds)
                ->delete();

            if ($deletedInBatch < 1) {
                return [
                    'candidate_count' => $candidateCount,
                    'deleted_count' => $deletedCount,
                    'stalled' => true,
                    'stalled_candidate_ids' => array_slice($deleteBatchIds, 0, 5),
                    'stalled_candidate_id_count' => count($deleteBatchIds),
                ];
            }

            $deletedCount += $deletedInBatch;
        }

        return [
            'candidate_count' => $candidateCount,
            'deleted_count' => $deletedCount,
            'stalled' => false,
            'stalled_candidate_ids' => [],
            'stalled_candidate_id_count' => 0,
        ];
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

    /**
     * @param  callable(): mixed  $cartResolver
     * @param  array<int|string, string|array{source?:string, filled?:bool, default?:mixed}>  $requestSchema
     * @param  list<string>  $cartItemAttributeKeys
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public function checkoutFailureContextFromCartResolver(
        Throwable $exception,
        mixed $request,
        callable $cartResolver,
        array $requestSchema,
        array $cartItemAttributeKeys = [],
        ?CarbonInterface $capturedAt = null,
        int $snapshotVersion = 1,
        array $extra = [],
    ): array {
        $cartContext = [
            'capture_failed' => true,
            'message' => 'checkout cart unavailable',
        ];

        try {
            $cartContext = $this->captureCart($cartResolver(), $cartItemAttributeKeys);
        } catch (Throwable $cartException) {
            $cartContext['exception'] = $cartException::class;
            $cartContext['message'] = $cartException->getMessage();
        }

        return $this->checkoutFailureContext(
            exception: $exception,
            requestSnapshot: $this->requestSnapshot($request, $requestSchema),
            cartContext: $cartContext,
            capturedAt: $capturedAt,
            snapshotVersion: $snapshotVersion,
            extra: $extra,
        );
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

    /**
     * @param  array<int|string, string|array{source?:string, filled?:bool, default?:mixed}>  $fields
     * @return array<string, mixed>
     */
    public function requestSnapshot(mixed $request, array $fields): array
    {
        $snapshot = [];

        foreach ($fields as $outputKey => $definition) {
            if (is_int($outputKey)) {
                if (! is_string($definition)) {
                    continue;
                }

                $outputKey = (string) $definition;
                $definition = [];
            }

            if (is_string($definition)) {
                $definition = ['source' => $definition];
            }

            $sourceKey = (string) ($definition['source'] ?? $outputKey);
            if (($definition['filled'] ?? false) === true) {
                $snapshot[$outputKey] = $this->requestFilled(
                    $request,
                    $sourceKey,
                    (bool) ($definition['default'] ?? false)
                );

                continue;
            }

            $snapshot[$outputKey] = $this->requestInput(
                $request,
                $sourceKey,
                $definition['default'] ?? null
            );
        }

        return $snapshot;
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
     * @param  array<int|string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}|string>  $schema
     * @return array<string, array{source?:string, type?:'array'|'bool'|'datetime'|'float'|'int'|'json'|'raw'|'string', default?:mixed}>
     */
    private function normalizeSnapshotSchema(array $schema): array
    {
        $normalized = [];

        foreach ($schema as $outputKey => $definition) {
            if (is_int($outputKey)) {
                if (! is_string($definition)) {
                    continue;
                }

                $outputKey = (string) $definition;
                $definition = [];
            }

            if (is_string($definition)) {
                $definition = ['source' => $definition];
            }

            $normalized[(string) $outputKey] = $definition;
        }

        return $normalized;
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

    /**
     * @param  class-string<Model>  $snapshotModel
     */
    private function assertSnapshotModel(string $snapshotModel): void
    {
        if (! is_subclass_of($snapshotModel, Model::class)) {
            throw new InvalidArgumentException('Snapshot model must extend '.Model::class.'.');
        }
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
