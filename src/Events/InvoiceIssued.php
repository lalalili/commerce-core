<?php

namespace Lalalili\CommerceCore\Events;

use Illuminate\Database\Eloquent\Model;

/**
 * 電子發票開立後派發。
 *
 * commerce-core 本身不含發票開立流程（屬 host / 後續發票套件邏輯），故僅提供事件
 * 類別供開立完成時派發、其他套件（如通知）監聽。
 */
final class InvoiceIssued
{
    public function __construct(
        public readonly Model $order,
        public readonly Model $invoice,
    ) {}
}
