<?php

namespace Lalalili\CommerceCore\Services;

class CouponReasonMessageService
{
    public const DEFAULT_REASON_KEY = '*';

    /**
     * @param  array<string, array<string, string>>  $messagesByKind
     */
    public function resolve(
        string $kind,
        ?string $reasonCode,
        array $messagesByKind,
        string $default = '',
    ): string {
        $messages = $messagesByKind[$kind] ?? [];

        if ($reasonCode !== null && array_key_exists($reasonCode, $messages)) {
            return $messages[$reasonCode];
        }

        if (array_key_exists(self::DEFAULT_REASON_KEY, $messages)) {
            return $messages[self::DEFAULT_REASON_KEY];
        }

        return $default;
    }

    /**
     * @param  array<string, string>  $promotionReasonOverrides
     */
    public function eligibilityFailure(
        string $kind,
        ?string $reason,
        string $memberDefault,
        string $promotionDefault,
        array $promotionReasonOverrides = [],
    ): string {
        if ($kind === 'promotion' && is_string($reason) && array_key_exists($reason, $promotionReasonOverrides)) {
            return $promotionReasonOverrides[$reason];
        }

        if (is_string($reason) && $reason !== '') {
            return $reason;
        }

        return $kind === 'promotion' ? $promotionDefault : $memberDefault;
    }
}
