<?php

namespace Lalalili\CommerceCore\Services;

class CheckoutCartCompletionService
{
    /**
     * @param  iterable<mixed>|mixed  $checkoutContent
     * @return list<string>
     */
    public function itemIds(mixed $checkoutContent, bool $preferKeys = false): array
    {
        if (! is_iterable($checkoutContent)) {
            return [];
        }

        $itemIds = [];
        foreach ($checkoutContent as $key => $item) {
            $itemId = $preferKeys
                ? $key
                : $this->itemId($item, $key);

            if ($itemId === null || $itemId === '') {
                continue;
            }

            $itemIds[] = (string) $itemId;
        }

        return $itemIds;
    }

    private function itemId(mixed $item, mixed $fallback): mixed
    {
        if (is_array($item) && array_key_exists('id', $item)) {
            return $item['id'];
        }

        if (is_object($item) && property_exists($item, 'id')) {
            return $item->id;
        }

        return $fallback;
    }

    /**
     * @param  iterable<mixed>|mixed  $checkoutContent
     * @return list<string>
     */
    public function complete(
        mixed $checkoutContent,
        callable $removeCartItem,
        callable $clearCheckout,
        bool $clearCheckoutFirst = false,
        bool $preferKeys = false,
    ): array {
        $itemIds = $this->itemIds($checkoutContent, $preferKeys);

        if ($clearCheckoutFirst) {
            $clearCheckout();
        }

        foreach ($itemIds as $itemId) {
            $removeCartItem($itemId);
        }

        if (! $clearCheckoutFirst) {
            $clearCheckout();
        }

        return $itemIds;
    }
}
