<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use InvalidArgumentException;

class CouponCodeGenerationPolicy
{
    /**
     * @param  iterable<int|string|BackedEnum>  $requiredUserIdTypes
     * @param  iterable<int|string|BackedEnum>  $uniquenessExemptTypes
     * @return array{
     *     type_value: int,
     *     user_id: int|null,
     *     count: int,
     *     max_attempts: int,
     *     should_check_uniqueness: bool
     * }
     */
    public function resolve(
        int|string|BackedEnum $typeId,
        ?int $userId = null,
        int $count = 1,
        int $maxAttempts = 5,
        iterable $requiredUserIdTypes = [],
        iterable $uniquenessExemptTypes = [],
    ): array {
        $typeValue = $this->intValue($typeId);

        if ($count < 1) {
            throw new InvalidArgumentException('Coupon code count must be greater than zero.');
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('Coupon code max attempts must be greater than zero.');
        }

        if ($this->typeMatches($typeValue, $requiredUserIdTypes) && $userId === null) {
            throw new InvalidArgumentException('User id is required for generating coupon number.');
        }

        $shouldCheckUniqueness = ! $this->typeMatches($typeValue, $uniquenessExemptTypes);

        return [
            'type_value' => $typeValue,
            'user_id' => $userId,
            'count' => $count,
            'max_attempts' => $shouldCheckUniqueness ? $maxAttempts : 1,
            'should_check_uniqueness' => $shouldCheckUniqueness,
        ];
    }

    private function intValue(int|string|BackedEnum $value): int
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
            if ($this->intValue($type) === $typeValue) {
                return true;
            }
        }

        return false;
    }
}
