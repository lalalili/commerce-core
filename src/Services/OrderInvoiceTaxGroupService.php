<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;

class OrderInvoiceTaxGroupService
{
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
}
