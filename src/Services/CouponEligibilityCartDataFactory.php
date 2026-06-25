<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Illuminate\Support\Collection;

class CouponEligibilityCartDataFactory
{
    /**
     * @param  iterable<mixed>  $lines
     * @param  iterable<int|string|BackedEnum>  $bookTypes
     * @param  iterable<int|string|BackedEnum>  $ebookTypes
     * @param  iterable<int|string>  $specificProductIds
     * @return array{
     *     order_total: float,
     *     all_amount: float,
     *     book_amount: float,
     *     ebook_amount: float,
     *     specific_products_amount: float,
     *     has_book: bool,
     *     has_ebook: bool,
     *     has_specific_products: bool
     * }
     */
    public function fromLines(
        iterable $lines,
        iterable $bookTypes,
        iterable $ebookTypes,
        iterable $specificProductIds = [],
        ?float $orderTotal = null,
    ): array {
        $normalizedLines = $this->normalizeLines($lines);
        $bookTypeValues = $this->normalizeScalarSet($bookTypes);
        $ebookTypeValues = $this->normalizeScalarSet($ebookTypes);
        $specificProductIdValues = $this->normalizeScalarSet($specificProductIds);

        $bookLines = $normalizedLines->filter(
            static fn (array $line): bool => in_array($line['type'], $bookTypeValues, true),
        );
        $ebookLines = $normalizedLines->filter(
            static fn (array $line): bool => in_array($line['type'], $ebookTypeValues, true),
        );
        $specificLines = $normalizedLines->filter(
            static fn (array $line): bool => in_array($line['id'], $specificProductIdValues, true),
        );

        $allAmount = $this->sumLineAmounts($normalizedLines);
        $bookAmount = $this->sumLineAmounts($bookLines);
        $ebookAmount = $this->sumLineAmounts($ebookLines);
        $specificProductsAmount = $this->sumLineAmounts($specificLines);

        return [
            'order_total' => $orderTotal ?? $allAmount,
            'all_amount' => $allAmount,
            'book_amount' => $bookAmount,
            'ebook_amount' => $ebookAmount,
            'specific_products_amount' => $specificProductsAmount,
            'has_book' => $bookAmount > 0,
            'has_ebook' => $ebookAmount > 0,
            'has_specific_products' => $specificProductsAmount > 0,
        ];
    }

    /**
     * @param  iterable<mixed>  $lines
     * @return Collection<int, array{id: string, type: string, list_price: float, quantity: float}>
     */
    private function normalizeLines(iterable $lines): Collection
    {
        return collect($lines)
            ->map(function (mixed $line): ?array {
                $id = data_get($line, 'id');
                if (! is_int($id) && ! is_string($id)) {
                    return null;
                }

                return [
                    'id' => (string) $id,
                    'type' => $this->normalizeScalar(data_get($line, 'type')),
                    'list_price' => (float) data_get($line, 'list_price', 0),
                    'quantity' => max((float) (data_get($line, 'quantity') ?? data_get($line, 'cart_quantity') ?? 1), 1.0),
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, array{id: string, type: string, list_price: float, quantity: float}>  $lines
     */
    private function sumLineAmounts(Collection $lines): float
    {
        return (float) $lines->sum(
            static fn (array $line): float => $line['list_price'] * $line['quantity'],
        );
    }

    /**
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    private function normalizeScalarSet(iterable $values): array
    {
        return array_values(collect($values)
            ->map(fn (mixed $value): string => $this->normalizeScalar($value))
            ->filter(static fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all());
    }

    private function normalizeScalar(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_int($value) || is_float($value) || is_string($value) ? (string) $value : '';
    }
}
