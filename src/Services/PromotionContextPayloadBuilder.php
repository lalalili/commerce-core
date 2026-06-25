<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use DateTimeInterface;

class PromotionContextPayloadBuilder
{
    /**
     * @param  object|array<string, mixed>  $event
     * @return array{
     *     type: int,
     *     sort: int|null,
     *     discountAmount: int|float|null,
     *     rebateGetAmount: int|float|null,
     *     eventId: int|null,
     *     name: string|null,
     *     rebateTriggerAmount: int|float|null,
     *     giftTriggerAmount: int|float|null,
     *     giftTriggerQuantity: int|float|null,
     *     giftProductCode: string|null,
     *     repeatable: bool|int|null,
     *     attributes: array<string, mixed>
     * }|null
     */
    public function payload(
        object|array $event,
        string $giftProductKey = 'gift_prod_no',
        bool $includeSortAttribute = true,
    ): ?array {
        $typeValue = $this->resolveTypeValue(data_get($event, 'type'));
        if ($typeValue === null) {
            return null;
        }

        $sort = $this->toNullableInt(data_get($event, 'sort'));
        $attributes = [
            'updated_at_timestamp' => $this->timestamp(data_get($event, 'updated_at')),
        ];

        if ($includeSortAttribute) {
            $attributes['sort'] = $sort;
        }

        return [
            'type' => $typeValue,
            'sort' => $sort,
            'discountAmount' => $this->toNullableNumber(data_get($event, 'discount_amount')),
            'rebateGetAmount' => $this->toNullableNumber(data_get($event, 'rebate_get_amount')),
            'eventId' => $this->toNullableInt(data_get($event, 'id')),
            'name' => $this->toNullableString(data_get($event, 'title')),
            'rebateTriggerAmount' => $this->toNullableNumber(data_get($event, 'rebate_trigger_amount')),
            'giftTriggerAmount' => $this->toNullableNumber(data_get($event, 'gift_trigger_amount')),
            'giftTriggerQuantity' => $this->toNullableNumber(data_get($event, 'gift_trigger_quantity')),
            'giftProductCode' => $this->toNullableString(data_get($event, $giftProductKey)),
            'repeatable' => $this->toNullableRepeatable(data_get($event, 'repeatable')),
            'attributes' => $attributes,
        ];
    }

    private function resolveTypeValue(mixed $type): ?int
    {
        if ($type instanceof BackedEnum && is_numeric($type->value)) {
            return (int) $type->value;
        }

        if (is_numeric($type)) {
            return (int) $type;
        }

        return null;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function toNullableNumber(mixed $value): int|float|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $numeric = (string) $value;

        if (str_contains($numeric, '.')) {
            return (float) $numeric;
        }

        return (int) $numeric;
    }

    private function toNullableString(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function toNullableRepeatable(mixed $value): bool|int|null
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function timestamp(mixed $value): ?int
    {
        if ($value instanceof DateTimeInterface) {
            return $value->getTimestamp();
        }

        $timestamp = data_get($value, 'timestamp');

        return is_numeric($timestamp) ? (int) $timestamp : null;
    }
}
