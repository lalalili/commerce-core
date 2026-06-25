<?php

namespace Lalalili\CommerceCore\Services;

class CouponInventoryService
{
    public function shouldTrackInventory(mixed $coupon): bool
    {
        return data_get($coupon, 'limit_qty') !== null;
    }

    /**
     * @param  callable(mixed): mixed  $decrement
     */
    public function reserve(mixed $coupon, callable $decrement): bool
    {
        if ($coupon === null) {
            return false;
        }

        if (! $this->shouldTrackInventory($coupon)) {
            return true;
        }

        return $this->affectedRows($decrement($coupon)) > 0;
    }

    public function canRestore(mixed $coupon): bool
    {
        $limitQuantity = data_get($coupon, 'limit_qty');
        $leftQuantity = data_get($coupon, 'left_qty');

        return is_numeric($limitQuantity)
            && is_numeric($leftQuantity)
            && (int) $leftQuantity < (int) $limitQuantity;
    }

    /**
     * @param  callable(mixed): mixed  $increment
     * @param  callable(mixed): mixed|null  $touch
     */
    public function restore(mixed $coupon, callable $increment, ?callable $touch = null): bool
    {
        if (! $this->canRestore($coupon)) {
            return false;
        }

        if ($this->affectedRows($increment($coupon)) < 1) {
            return false;
        }

        if ($touch !== null) {
            $touch($coupon);
        }

        return true;
    }

    private function affectedRows(mixed $result): int
    {
        if (is_bool($result)) {
            return $result ? 1 : 0;
        }

        return is_numeric($result) ? (int) $result : 0;
    }
}
