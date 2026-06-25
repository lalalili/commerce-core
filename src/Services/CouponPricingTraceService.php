<?php

namespace Lalalili\CommerceCore\Services;

class CouponPricingTraceService
{
    public const DEFAULT_MAX_COUPON_TRACE_ENTRIES = 20;

    /**
     * @param  array<string, mixed>  $pricingTrace
     * @param  array<string, mixed>|list<array<string, mixed>>  $entries
     * @return array<string, mixed>
     */
    public function appendCouponTrace(
        array $pricingTrace,
        array $entries,
        int $maxEntries = self::DEFAULT_MAX_COUPON_TRACE_ENTRIES,
    ): array {
        $normalizedEntries = $this->normalizeEntries($entries);
        if ($normalizedEntries === []) {
            return $pricingTrace;
        }

        $couponTrace = $this->normalizeEntries($pricingTrace['coupon'] ?? []);
        $pricingTrace['coupon'] = $this->mergeLatestByIdentity(
            $couponTrace,
            $normalizedEntries,
            $maxEntries,
        );

        return $pricingTrace;
    }

    /**
     * @param  array<string, mixed>  $pricingTrace
     * @return array<string, mixed>
     */
    public function clearCouponTrace(array $pricingTrace, ?string $kind = null, ?string $code = null): array
    {
        $couponTrace = $this->normalizeEntries($pricingTrace['coupon'] ?? []);
        if ($couponTrace === []) {
            return $pricingTrace;
        }

        $codeValue = is_string($code) ? trim($code) : '';
        if ($kind === null && $codeValue === '') {
            unset($pricingTrace['coupon']);

            return $pricingTrace;
        }

        $pricingTrace['coupon'] = array_values(array_filter(
            $couponTrace,
            static function (array $entry) use ($kind, $codeValue): bool {
                $matchesKind = $kind === null || (string) ($entry['kind'] ?? '') === $kind;
                $matchesCode = $codeValue === '' || (string) ($entry['code'] ?? '') === $codeValue;

                return ! ($matchesKind && $matchesCode);
            }
        ));

        if ($pricingTrace['coupon'] === []) {
            unset($pricingTrace['coupon']);
        }

        return $pricingTrace;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function normalizeEntries(mixed $entries): array
    {
        if (! is_array($entries)) {
            return [];
        }

        if (array_key_exists('stage', $entries)) {
            $entry = $this->stringKeyedEntry($entries);

            return $entry === [] ? [] : [$entry];
        }

        $normalized = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalizedEntry = $this->stringKeyedEntry($entry);
            if ($normalizedEntry !== []) {
                $normalized[] = $normalizedEntry;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array<string, mixed>>  $incoming
     * @return list<array<string, mixed>>
     */
    public function mergeLatestByIdentity(array $existing, array $incoming, int $maxEntries = self::DEFAULT_MAX_COUPON_TRACE_ENTRIES): array
    {
        $mergedTrace = [];

        foreach (array_merge($existing, $incoming) as $entry) {
            $traceKey = $this->identityKey($entry);
            unset($mergedTrace[$traceKey]);
            $mergedTrace[$traceKey] = $entry;
        }

        return array_values(array_slice($mergedTrace, -max(1, $maxEntries), null, true));
    }

    /**
     * @param  array<int|string, mixed>  $entry
     * @return array<string, mixed>
     */
    private function stringKeyedEntry(array $entry): array
    {
        $normalized = [];

        foreach ($entry as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    private function identityKey(array $entry): string
    {
        $identifier = $entry['id'] ?? null;

        if ($identifier === null || $identifier === '') {
            $identifier = $entry['code'] ?? '';
        }

        return implode('|', [
            $this->stringValue($entry['stage'] ?? null),
            $this->stringValue($entry['source'] ?? null),
            $this->stringValue($entry['kind'] ?? null),
            $this->stringValue($identifier),
        ]);
    }

    private function stringValue(mixed $value): string
    {
        return is_int($value) || is_float($value) || is_string($value) ? (string) $value : '';
    }
}
