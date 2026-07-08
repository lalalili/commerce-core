<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;

class OrderInvoiceTaxGroupService
{
    public function __construct(
        private readonly OrderDetailAdjustmentService $adjustments,
        private readonly ModelAttributeMapper $attributes,
    ) {}

    /**
     * @return array{order_number: string, tax_type: int}|null
     */
    public function parseOrderNoWithTax(string $orderNoWithTax): ?array
    {
        [$orderNumber, $taxType] = array_pad(explode('-', $orderNoWithTax, 2), 2, null);

        if ($orderNumber === null || $orderNumber === '' || ! is_numeric($taxType)) {
            return null;
        }

        return [
            'order_number' => $orderNumber,
            'tax_type' => (int) $taxType,
        ];
    }

    public function orderNoWithTax(Model $order, int|string $taxType, string $orderNumberAttribute = 'number'): string
    {
        return (string) $order->getAttribute($orderNumberAttribute).'-'.$taxType;
    }

    /**
     * @param  array<int|string, list<array<string, mixed>>>  $orderDetailsWithTax
     * @return array<int, list<array{title: string, sales_price: int, qty: int}>>
     */
    public function normalizeOrderDetailsWithTax(array $orderDetailsWithTax): array
    {
        $normalized = [];

        foreach ($orderDetailsWithTax as $taxType => $items) {
            $normalized[(int) $taxType] = array_map(
                static fn (array $item): array => [
                    'title' => (string) ($item['title'] ?? ($item['product_title'] ?? '')),
                    'sales_price' => (int) ($item['sales_price'] ?? 0),
                    'qty' => (int) ($item['qty'] ?? 0),
                ],
                $items
            );
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  array<int|string, list<T>>  $groups
     * @return array<int, list<T>>
     */
    public function normalizeTaxGroupKeys(array $groups): array
    {
        $normalized = [];

        foreach ($groups as $taxType => $details) {
            $normalized[(int) $taxType] = $details;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  callable(Model): (array<string, mixed>|null)  $detailResolver
     * @param  array{details_relation?: string, amount_key?: string, taxable_amount_key?: string, force_tax_type?: int|string|null}  $options
     * @return list<array{detail: array<string, mixed>, tax_type: int, taxable_amount: int}>
     */
    public function orderDetailTaxItems(Model $order, ?callable $detailResolver = null, array $options = []): array
    {
        $detailsRelation = $this->detailsRelation($options);
        $amountKey = $options['amount_key'] ?? 'sales_price';
        $taxableAmountKey = $options['taxable_amount_key'] ?? $amountKey;
        $forceTaxType = $options['force_tax_type'] ?? null;

        $order->loadMissing([$detailsRelation.'.product']);

        $items = [];

        foreach ($order->getRelationValue($detailsRelation) ?? [] as $detail) {
            if (! $detail instanceof Model) {
                continue;
            }

            $detailPayload = $detailResolver === null ? $detail->toArray() : $detailResolver($detail);

            if ($detailPayload === null || $detailPayload === []) {
                continue;
            }

            $product = $detail->getRelationValue('product');

            if (! $product instanceof Model && $forceTaxType === null) {
                continue;
            }

            $taxType = $forceTaxType !== null
                ? (int) $forceTaxType
                : (((int) $this->attributes->value($product, 'products', 'tax', 1)) === 0 ? 0 : 1);
            $quantity = (int) data_get($detail, $this->attributes->column('order_details', 'qty', 'qty') ?? 'qty', 0);
            $taxableAmount = $taxType === 1
                ? (int) round((float) data_get($detail, $taxableAmountKey, 0) * $quantity)
                : 0;

            $items[] = [
                'detail' => $detailPayload,
                'tax_type' => $taxType,
                'taxable_amount' => $taxableAmount,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $discountLine
     * @param  array{details_relation?: string, amount_key?: string, taxable_amount_key?: string, shipping_line?: array<string, mixed>|null, shipping_amount?: mixed, force_tax_type?: int|string|null, taxable_key?: int|string, tax_free_key?: int|string}  $options
     * @param  callable(Model): (array<string, mixed>|null)  $detailResolver
     * @return array<int, list<array<string, mixed>>>
     */
    public function groupOrderDetailsByTaxBucket(
        Model $order,
        mixed $discountAmount,
        array $discountLine,
        array $options = [],
        ?callable $detailResolver = null,
    ): array {
        return $this->normalizeTaxGroupKeys($this->groupDetailsByTaxBucket(
            $this->orderDetailTaxItems($order, $detailResolver, $options),
            $discountAmount,
            $discountLine,
            $options,
        ));
    }

    /**
     * @param  list<array{detail: array<string, mixed>, tax_type: int|string, taxable_amount?: mixed}>  $items
     * @param  array<string, mixed>  $discountLine
     * @param  array{shipping_line?: array<string, mixed>|null, shipping_amount?: mixed, force_tax_type?: int|string|null, taxable_key?: int|string, tax_free_key?: int|string, amount_key?: string}  $options
     * @return array<int|string, list<array<string, mixed>>>
     */
    public function groupDetailsByTaxBucket(
        array $items,
        mixed $discountAmount,
        array $discountLine,
        array $options = [],
    ): array {
        $taxableKey = $options['taxable_key'] ?? 1;
        $taxFreeKey = $options['tax_free_key'] ?? 0;
        $amountKey = $options['amount_key'] ?? 'sales_price';
        $forceTaxType = $options['force_tax_type'] ?? null;
        $shippingLine = $options['shipping_line'] ?? null;
        $shippingAmount = $options['shipping_amount'] ?? 0;

        if ($forceTaxType !== null) {
            $groups = [
                $forceTaxType => array_map(
                    static fn (array $item): array => $item['detail'],
                    $items,
                ),
            ];

            if (is_array($shippingLine) && (float) $shippingAmount > 0) {
                $groups[$forceTaxType][] = $shippingLine;
            }

            $discount = $this->adjustments->discountLine($discountAmount, $discountLine, $amountKey);
            if ($discount !== null) {
                $groups[$forceTaxType][] = $discount;
            }

            return $this->normalizeGroupedDetails($groups);
        }

        $groups = [];
        $taxableAmount = 0;

        foreach ($items as $item) {
            $taxType = $item['tax_type'];
            $groups[$taxType][] = $item['detail'];

            if ((string) $taxType === (string) $taxableKey) {
                $taxableAmount += (int) round((float) ($item['taxable_amount'] ?? 0));
            }
        }

        if (is_array($shippingLine) && (float) $shippingAmount > 0) {
            $groups[$taxableKey][] = $shippingLine;
            $taxableAmount += (int) round((float) $shippingAmount);
        }

        foreach ($this->adjustments->discountLinesByTaxBucket(
            $discountAmount,
            $taxableAmount,
            $discountLine,
            $taxableKey,
            $taxFreeKey,
            $amountKey,
        ) as $taxType => $lines) {
            foreach ($lines as $line) {
                $groups[$taxType][] = $line;
            }
        }

        return $this->normalizeGroupedDetails($groups);
    }

    /**
     * @param  array<int|string, list<array<string, mixed>>>  $orderDetailsWithTax
     * @param  callable(string, int, Model, list<array{title: string, sales_price: int, qty: int}>): array<string, mixed>  $issuer
     * @return list<array<string, mixed>>
     */
    public function issueTaxGroups(Model $order, array $orderDetailsWithTax, callable $issuer): array
    {
        $responses = [];

        foreach ($this->normalizeOrderDetailsWithTax($orderDetailsWithTax) as $taxType => $details) {
            $responses[] = $issuer(
                $this->orderNoWithTax($order, $taxType),
                $taxType,
                $order,
                $details,
            );
        }

        return $responses;
    }

    /**
     * @template T of array<string, mixed>
     *
     * @param  array<int|string, list<T>>  $orderDetailsWithTax
     * @return list<T>|null
     */
    public function selectedTaxGroupDetails(array $orderDetailsWithTax, int|string $taxType): ?array
    {
        if (isset($orderDetailsWithTax[$taxType])) {
            return $orderDetailsWithTax[$taxType];
        }

        $numericTaxType = (int) $taxType;
        if (isset($orderDetailsWithTax[$numericTaxType])) {
            return $orderDetailsWithTax[$numericTaxType];
        }

        $stringTaxType = (string) $taxType;
        if (isset($orderDetailsWithTax[$stringTaxType])) {
            return $orderDetailsWithTax[$stringTaxType];
        }

        return null;
    }

    /**
     * @param  array<int|string, list<array<string, mixed>>>  $orderDetailsWithTax
     * @param  callable(string, int, Model, list<array{title: string, sales_price: int, qty: int}>): array<string, mixed>  $issuer
     * @return array<string, mixed>
     */
    public function issueSelectedTaxGroup(
        string $orderNoWithTax,
        Model $order,
        array $orderDetailsWithTax,
        callable $issuer,
    ): array {
        $parsed = $this->parseOrderNoWithTax($orderNoWithTax);
        if ($parsed === null) {
            return [];
        }

        $details = $this->selectedTaxGroupDetails(
            $this->normalizeOrderDetailsWithTax($orderDetailsWithTax),
            $parsed['tax_type'],
        );
        if ($details === null) {
            return [];
        }

        return $issuer($orderNoWithTax, $parsed['tax_type'], $order, $details);
    }

    /**
     * @param  callable(string): (Model|null)  $orderResolver
     * @param  callable(Model): array<int|string, list<array<string, mixed>>>  $orderDetailsResolver
     * @param  callable(string, int, Model, list<array{title: string, sales_price: int, qty: int}>): array<string, mixed>  $issuer
     * @param  callable(string, array<string, mixed>): void|null  $failure
     * @return array<string, mixed>
     */
    public function issueSelectedTaxGroupByOrderNoWithTax(
        string $orderNoWithTax,
        callable $orderResolver,
        callable $orderDetailsResolver,
        callable $issuer,
        ?callable $failure = null,
    ): array {
        $parsed = $this->parseOrderNoWithTax($orderNoWithTax);

        if ($parsed === null) {
            if ($failure !== null) {
                $failure('invalid_order_no_with_tax', [
                    'order_no_with_tax' => $orderNoWithTax,
                ]);
            }

            return [];
        }

        $order = $orderResolver($parsed['order_number']);

        if (! $order instanceof Model) {
            if ($failure !== null) {
                $failure('order_not_found', [
                    'order_number' => $parsed['order_number'],
                ]);
            }

            return [];
        }

        $orderDetailsWithTax = $orderDetailsResolver($order);

        if ($this->selectedTaxGroupDetails($orderDetailsWithTax, $parsed['tax_type']) === null) {
            if ($failure !== null) {
                $failure('tax_group_not_found', [
                    'order_number' => $parsed['order_number'],
                    'tax_type' => $parsed['tax_type'],
                ]);
            }

            return [];
        }

        return $this->issueSelectedTaxGroup(
            $orderNoWithTax,
            $order,
            $orderDetailsWithTax,
            $issuer,
        );
    }

    /**
     * @param  array<int|string, array<int, array<string, mixed>>>  $groups
     * @return array<int|string, list<array<string, mixed>>>
     */
    private function normalizeGroupedDetails(array $groups): array
    {
        ksort($groups);

        return array_map(
            static fn (array $details): array => array_values($details),
            $groups,
        );
    }

    /**
     * @param  array{details_relation?: string}  $options
     */
    private function detailsRelation(array $options = []): string
    {
        $relation = $options['details_relation'] ?? config('commerce.relationships.order_details', 'details');

        return is_string($relation) && $relation !== '' ? $relation : 'details';
    }
}
