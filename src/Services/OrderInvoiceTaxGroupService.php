<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;

class OrderInvoiceTaxGroupService
{
    public function __construct(private readonly OrderDetailAdjustmentService $adjustments) {}

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

        $normalized = $this->normalizeOrderDetailsWithTax($orderDetailsWithTax);
        $details = $normalized[$parsed['tax_type']] ?? null;
        if ($details === null) {
            return [];
        }

        return $issuer($orderNoWithTax, $parsed['tax_type'], $order, $details);
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
}
