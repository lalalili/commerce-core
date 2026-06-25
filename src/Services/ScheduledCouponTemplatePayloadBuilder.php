<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Carbon\CarbonInterface;

class ScheduledCouponTemplatePayloadBuilder
{
    /**
     * @param  array<string, mixed>  $template
     * @param  array{
     *     title?: string|null,
     *     active_column?: string|null,
     *     active_value?: mixed,
     *     created_by?: mixed,
     *     include_updated_at?: bool,
     *     format_dates?: bool,
     *     end_date_end_of_day?: bool,
     *     decode_scope_products?: bool,
     *     scope_products_empty_as_null?: bool,
     *     use_effective_days_for_start_date?: bool,
     *     start_date?: CarbonInterface|string,
     *     created_at?: CarbonInterface|string,
     *     updated_at?: CarbonInterface|string,
     * }  $options
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function build(
        array $template,
        int $userId,
        int|string|BackedEnum $memberType,
        string $code,
        CarbonInterface|string $now,
        array $options = [],
        array $overrides = [],
    ): array {
        $formatDates = (bool) ($options['format_dates'] ?? false);
        $startDate = $options['start_date'] ?? (
            (bool) ($options['use_effective_days_for_start_date'] ?? false)
                ? $this->resolveStartDate($template['effective_days'] ?? null, $now)
                : $now
        );
        $createdAt = $options['created_at'] ?? $now;
        $updatedAt = $options['updated_at'] ?? $now;

        $payload = [
            'title' => $options['title'] ?? (string) ($template['title'] ?? ''),
            'code' => $code,
            'amount' => $template['amount'] ?? null,
            'start_date' => $this->formatDate($startDate, $formatDates),
            'end_date' => $this->formatNullableDate(
                $this->resolveEndDate($template['available_days'] ?? null, $now, (bool) ($options['end_date_end_of_day'] ?? false)),
                $formatDates,
            ),
            'trigger_amount' => $template['trigger_amount'] ?? null,
            'type' => $this->scalarValue($memberType),
            'scope' => $template['scope'] ?? null,
            'scope_products' => $this->resolveScopeProducts(
                $template['scope_products'] ?? null,
                (bool) ($options['decode_scope_products'] ?? false),
                (bool) ($options['scope_products_empty_as_null'] ?? true),
            ),
            'user_id' => $userId,
            'created_at' => $this->formatDate($createdAt, $formatDates),
        ];

        $activeColumn = $options['active_column'] ?? null;
        if (is_string($activeColumn) && $activeColumn !== '') {
            $payload[$activeColumn] = $options['active_value'] ?? 1;
        }

        if (array_key_exists('created_by', $options)) {
            $payload['created_by'] = $options['created_by'];
        }

        if ((bool) ($options['include_updated_at'] ?? false)) {
            $payload['updated_at'] = $this->formatDate($updatedAt, $formatDates);
        }

        return array_merge($payload, $overrides);
    }

    public function extractTitleSuffix(?string $title): string
    {
        $title = (string) $title;

        if ($title === '') {
            return '';
        }

        if (! str_contains($title, '_')) {
            return $title;
        }

        $parts = explode('_', $title, 2);

        return $parts[1] !== '' ? $parts[1] : $title;
    }

    public function resolveEndDate(mixed $availableDays, CarbonInterface|string $now, bool $endOfDay = false): CarbonInterface|string|null
    {
        $days = (int) ($availableDays ?? 0);

        if ($days <= 0) {
            return null;
        }

        if (! $now instanceof CarbonInterface) {
            return null;
        }

        $endDate = $now->copy()->addDays($days);

        return $endOfDay ? $endDate->endOfDay() : $endDate;
    }

    public function resolveStartDate(mixed $effectiveDays, CarbonInterface|string $now): CarbonInterface|string
    {
        $days = (int) ($effectiveDays ?? 0);

        if ($days <= 0 || ! $now instanceof CarbonInterface) {
            return $now;
        }

        return $now->copy()->addDays($days);
    }

    /**
     * @return array<int, mixed>|string|null
     */
    public function resolveScopeProducts(mixed $scopeProducts, bool $decodeJson = false, bool $emptyAsNull = true): array|string|null
    {
        if ($scopeProducts === null) {
            return null;
        }

        if ($scopeProducts === '' && $emptyAsNull) {
            return null;
        }

        if (is_array($scopeProducts)) {
            return $scopeProducts;
        }

        if (! is_string($scopeProducts) || ! $decodeJson) {
            return $scopeProducts;
        }

        $decoded = json_decode($scopeProducts, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function scalarValue(int|string|BackedEnum $value): int|string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return $value;
    }

    private function formatDate(CarbonInterface|string $value, bool $formatDates): CarbonInterface|string
    {
        if ($formatDates && $value instanceof CarbonInterface) {
            return $value->toDateTimeString();
        }

        return $value;
    }

    private function formatNullableDate(CarbonInterface|string|null $value, bool $formatDates): CarbonInterface|string|null
    {
        if ($value === null) {
            return null;
        }

        return $this->formatDate($value, $formatDates);
    }
}
