<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;

class CouponFormPayloadBuilder
{
    /**
     * @param  array<string, mixed>  $data
     * @param  iterable<int|string|BackedEnum>  $memberTypes
     * @param  iterable<int|string|BackedEnum>  $promotionTypes
     * @param  iterable<int|string|BackedEnum>  $scheduledPrefixTypes
     * @param  iterable<int|string|BackedEnum>  $lineTemplateTypes
     * @param  callable(int, array<string, mixed>): string|null  $couponCodeFactory
     * @param  callable(): (int|string|null)  $lineTemplateSequenceFactory
     * @return array<string, mixed>
     */
    public function prepareCreate(
        array $data,
        iterable $memberTypes = [],
        iterable $promotionTypes = [],
        iterable $scheduledPrefixTypes = [],
        iterable $lineTemplateTypes = [],
        ?callable $couponCodeFactory = null,
        ?callable $lineTemplateSequenceFactory = null,
        string $scheduledTitlePrefix = '排程_',
        string $titleKey = 'title',
        string $typeKey = 'type',
        string $codeKey = 'code',
        string $limitQuantityKey = 'limit_qty',
        string $leftQuantityKey = 'left_qty',
    ): array {
        $typeValue = $this->typeValue($data[$typeKey] ?? 0);
        $data[$typeKey] = $typeValue;

        if ($this->typeMatches($typeValue, $scheduledPrefixTypes)) {
            $data[$titleKey] = $this->prefixedTitle($data[$titleKey] ?? '', $scheduledTitlePrefix);
        }

        if ($couponCodeFactory !== null && (
            $this->typeMatches($typeValue, $memberTypes)
            || $this->typeMatches($typeValue, $scheduledPrefixTypes)
        )) {
            $data[$codeKey] = $couponCodeFactory($typeValue, $data);
        }

        if ($this->typeMatches($typeValue, $promotionTypes)) {
            $data[$leftQuantityKey] = $data[$limitQuantityKey] ?? null;
        }

        if ($lineTemplateSequenceFactory !== null && $this->typeMatches($typeValue, $lineTemplateTypes)) {
            $data[$codeKey] = ((string) ($data[$titleKey] ?? '')).'_'.$lineTemplateSequenceFactory();
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  iterable<int|string|BackedEnum>  $promotionTypes
     * @param  iterable<int|string|BackedEnum>  $scheduledPrefixTypes
     * @return array<string, mixed>
     */
    public function prepareSave(
        array $data,
        iterable $promotionTypes = [],
        iterable $scheduledPrefixTypes = [],
        string $scheduledTitlePrefix = '排程_',
        string $titleKey = 'title',
        string $typeKey = 'type',
        string $limitQuantityKey = 'limit_qty',
        string $leftQuantityKey = 'left_qty',
        string $increaseQuantityKey = 'increase_qty',
    ): array {
        $typeValue = $this->typeValue($data[$typeKey] ?? 0);
        $data[$typeKey] = $typeValue;

        if ($this->typeMatches($typeValue, $scheduledPrefixTypes)) {
            $data[$titleKey] = $this->prefixedTitle($data[$titleKey] ?? '', $scheduledTitlePrefix);
        }

        if ($this->typeMatches($typeValue, $promotionTypes) && array_key_exists($increaseQuantityKey, $data)) {
            $increaseQuantity = (int) $data[$increaseQuantityKey];

            if ($increaseQuantity !== 0) {
                $data[$leftQuantityKey] = ((int) ($data[$leftQuantityKey] ?? 0)) + $increaseQuantity;
                $data[$limitQuantityKey] = ((int) ($data[$limitQuantityKey] ?? 0)) + $increaseQuantity;
            }

            unset($data[$increaseQuantityKey]);
        }

        return $data;
    }

    private function typeValue(int|string|BackedEnum $value): int
    {
        if ($value instanceof BackedEnum) {
            return (int) $value->value;
        }

        return (int) $value;
    }

    /**
     * @param  iterable<int|string|BackedEnum>  $types
     */
    private function typeMatches(int $typeValue, iterable $types): bool
    {
        foreach ($types as $type) {
            if ($this->typeValue($type) === $typeValue) {
                return true;
            }
        }

        return false;
    }

    private function prefixedTitle(mixed $title, string $prefix): string
    {
        $title = (string) $title;

        return str_starts_with($title, $prefix) ? $title : $prefix.$title;
    }
}
