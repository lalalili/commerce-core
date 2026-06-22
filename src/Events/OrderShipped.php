<?php

namespace Lalalili\CommerceCore\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * 訂單出貨後派發。
 *
 * commerce-core 本身不含出貨流程（屬 host 物流邏輯），故僅提供事件類別供 host /
 * 後續金物流套件在出貨完成時派發、其他套件（如發票、通知）監聽。
 */
final class OrderShipped
{
    public function __construct(
        public readonly Model $order,
        public readonly ?string $trackingNumber = null,
        public readonly ?int $updatedBy = null,
    ) {}
}
