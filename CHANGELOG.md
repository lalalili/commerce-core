# Changelog

All notable changes to `lalalili/commerce-core` are documented in this file.

## [1.71.0] - 2026-07-07

### Added

- `CouponCartConditionPayloadBuilder` 支援免運券 kind `free_shipping`
  (type=`shipping_coupon`、target=`subtotal`);coupon condition order 改讀
  config `discount.ordering.coupon.*`(與 lalalili/discount 同一 single source),
  類別常數標記 deprecated 僅作 fallback。

> 以下 1.0.0 ~ 1.70.0 由 git 歷史重建。這 70 個版本全部發佈於
> 2026-06-25 同一天,等同於每個 commit 自動打一個 minor tag,
> 版本號因此不具備溝通意義。後續請依
> [SEMVER.md](https://github.com/lalalili/.github/blob/main/SEMVER.md)
> 的規則決定版號級距。

## [1.70.0] - 2026-06-25

### Added

- 新增發券 payload insert 正規化

## [1.69.0] - 2026-06-25

### Added

- 新增排程優惠券範本 payload helper

## [1.68.1] - 2026-06-25

### Fixed

- 補強優惠券資料 factory 型別契約

## [1.68.0] - 2026-06-25

### Added

- 新增優惠券資料 DTO factory

## [1.67.0] - 2026-06-25

### Added

- 新增 CRUD 權限 policy 基底

## [1.66.0] - 2026-06-25

### Added

- 新增 query builder insert helper

## [1.65.0] - 2026-06-25

### Added

- 支援排程券生效天數起始日

## [1.64.0] - 2026-06-25

### Added

- 新增優惠券表單 payload builder

## [1.63.0] - 2026-06-25

### Added

- 支援 host attributes coupon id trace payload

## [1.62.1] - 2026-06-25

### Fixed

- 修正 coupon trace context 型別文件

## [1.62.0] - 2026-06-25

### Added

- 支援 trace-like coupon pricing trace input

## [1.61.0] - 2026-06-25

### Added

- 新增優惠券 reason message 服務

## [1.60.0] - 2026-06-25

### Added

- 新增促銷 context payload builder

## [1.59.0] - 2026-06-25

### Added

- 新增優惠券 cart condition payload builder

## [1.58.0] - 2026-06-25

### Added

- 新增優惠券 pricing trace context 服務

## [1.57.0] - 2026-06-25

### Added

- 新增排程優惠券模板 payload builder

## [1.56.0] - 2026-06-25

### Added

- 新增 promotion refresh input 組裝服務

## [1.55.0] - 2026-06-25

### Added

- 新增 coupon cart pricing trace service

## [1.54.0] - 2026-06-25

### Changed

- 補齊 commerce-core singleton 註冊

## [1.53.0] - 2026-06-25

### Added

- 共用 coupon code generation policy

## [1.52.0] - 2026-06-25

### Added

- 共用 coupon eligibility cart data factory

## [1.51.0] - 2026-06-25

### Added

- 共用 coupon inventory helper

## [1.50.0] - 2026-06-25

### Added

- 共用 coupon data payload resolver

## [1.49.0] - 2026-06-25

### Added

- 共用 cart promotion line resolver

## [1.48.0] - 2026-06-25

### Added

- 共用 coupon trace payload resolver

## [1.47.0] - 2026-06-25

### Added

- 共用 coupon checkout adapter helper

## [1.46.0] - 2026-06-25

### Added

- 共用發票排程派發 helper

## [1.45.0] - 2026-06-25

### Added

- 共用 checkout order builder helper

## [1.44.0] - 2026-06-25

### Added

- 共用 checkout cart completion helper

## [1.43.0] - 2026-06-25

### Added

- 共用 coupon pricing trace entry payload factory

## [1.42.0] - 2026-06-25

### Added

- 共用 cart item attributes 正規化

## [1.41.0] - 2026-06-25

### Added

- 共用 promotion refresh pipeline metadata

## [1.40.0] - 2026-06-25

### Added

- 共用 coupon pricing trace 操作

## [1.39.0] - 2026-06-25

### Added

- 共用 checkout order data factory

## [1.38.0] - 2026-06-25

### Added

- 共用指定稅別發票開立流程

## [1.37.0] - 2026-06-25

### Added

- 共用 checkout snapshot prune helper

## [1.36.0] - 2026-06-25

### Added

- 共用 checkout success snapshot persistence

## [1.35.0] - 2026-06-25

### Added

- 共用 checkout failure cart capture context

## [1.34.0] - 2026-06-25

### Added

- 新增結帳 guard 失敗原因 helper

## [1.33.1] - 2026-06-25

### Fixed

- 保留購物車明細快照泛型型別

## [1.33.0] - 2026-06-25

### Added

- 新增購物車明細快照 builder

## [1.32.0] - 2026-06-25

### Added

- 新增結帳一致性摘要 helper

## [1.31.1] - 2026-06-25

### Fixed

- 放寬結帳快照 schema 型別

## [1.31.0] - 2026-06-25

### Added

- 新增結帳成功快照記錄 helper

## [1.30.0] - 2026-06-25

### Added

- 新增訂單會計明細 helper

## [1.29.0] - 2026-06-25

### Added

- 新增訂單明細稅別分組 helper

## [1.28.0] - 2026-06-25

### Added

- 支援指定商品權益授權時間

## [1.27.0] - 2026-06-25

### Added

- 新增訂單折扣調整明細 helper

## [1.26.0] - 2026-06-25

### Added

- 新增信用卡號付款記錄 helper

## [1.25.0] - 2026-06-25

### Added

- 新增稅別分組鍵正規化 helper

## [1.24.0] - 2026-06-25

### Added

- 新增購物車明細基礎值 helper

## [1.23.0] - 2026-06-25

### Added

- 新增付款完成結果 helper

## [1.22.0] - 2026-06-25

### Added

- 新增訂單取消結果 helper

## [1.21.0] - 2026-06-25

### Added

- 新增付款記錄回應值讀取 helper

## [1.20.0] - 2026-06-25

### Added

- 新增訂單會計明細附加 helper

## [1.19.0] - 2026-06-25

### Added

- 新增待出貨生命週期 helper

## [1.18.0] - 2026-06-25

### Fixed

- 修正相同出貨狀態的出貨時間同步

## [1.17.0] - 2026-06-25

### Added

- 新增指定稅別明細查找 helper

## [1.16.0] - 2026-06-25

### Added

- 新增訂單明細稅別分桶 helper

## [1.15.0] - 2026-06-25

### Added

- 新增 request snapshot helper

## [1.14.0] - 2026-06-25

### Added

- 新增 checkout line snapshot 完整性 helper

## [1.13.0] - 2026-06-25

### Added

- 新增 checkout consistency helper

## [1.12.0] - 2026-06-25

### Added

- 新增 checkout line snapshot 摘要工具

## [1.11.0] - 2026-06-25

### Added

- 新增 checkout success payload helper

## [1.10.0] - 2026-06-25

### Added

- 新增 checkout request snapshot helper

## [1.9.0] - 2026-06-25

### Added

- 共用 checkout 失敗快照內容

## [1.8.0] - 2026-06-25

### Added

- 共用 checkout snapshot 持久化屬性

## [1.7.0] - 2026-06-25

### Added

- 新增訂單初始狀態解析器

## [1.6.0] - 2026-06-25

### Added

- 擴充 commerce-core 編號產生工具

## [1.5.0] - 2026-06-25

### Added

- 新增 invoice tax group orchestration 服務

## [1.4.0] - 2026-06-25

### Added

- 新增 order detail adjustment 服務

## [1.3.0] - 2026-06-25

### Added

- 新增 order detail row 正規化工具

## [1.2.0] - 2026-06-25

### Added

- 新增 cart line snapshot 輔助方法

## [1.1.0] - 2026-06-25

### Added

- 新增 checkout snapshot 正規化服務

## [0.1.10] - 2026-06-24

### Added

- `PaymentApplicationData` and `PaymentApplicationOutcome` for gateway-agnostic payment result application.
- `PaymentApplicationService::apply()` for recording payment logs, applying paid/refunded lifecycle transitions, handling amount mismatches, and updating payment status messages.

### Compatibility

- Additive; `commerce-core` does not depend on `commerce-payment`, and host applications can keep existing gateway adapters while delegating normalized payment outcomes to the new service.

## [0.1.9] - 2026-06-24

### Added

- `OrderLifecycleService::markShipped()` and `markFinished()` for fulfillment lifecycle transitions.
- `OrderFulfillmentLifecycleHook` for optional host callbacks after shipped and finished transitions.
- `OrderFinished` event and `OrderShipped` dispatching from `markShipped()`.
- `PaymentLogService::record()` for upserting gateway payment logs by order number and status code.
- `commerce.statuses.order.shipping` and `commerce.statuses.order.finished` status mappings.

### Compatibility

- Additive; existing lifecycle hooks remain valid and do not need to implement the fulfillment hook contract.

## [0.1.8] - 2026-06-24

### Added

- `OrderLifecycleHook` and `commerce.lifecycle.hooks` for host-specific side effects after paid, cancelled, and refunded lifecycle transitions.
- `OrderLifecycleService::markRefunded()` for marking payment status as refunded without cancelling the order or revoking entitlements.

### Compatibility

- Additive; existing `OrderLifecycleService` methods keep their public signatures.

## [0.1.7] - 2026-06-22

### Added

- Order lifecycle domain events under `Lalalili\CommerceCore\Events`: `OrderCreated`, `OrderPaid`, `OrderCancelled` are dispatched (after commit, on real state transitions only) from `OrderLifecycleService::create()` / `markPaid()` / `cancel()`. `OrderShipped` and `InvoiceIssued` event classes are provided for hosts and downstream packages to dispatch (commerce-core owns no shipping/invoicing flow).

### Compatibility

- Additive; idempotent re-calls do not re-dispatch. No interface or schema changes.

## [0.1.6] - 2026-06-22

### Added

- Unit tests covering `EntitlementService`, `OrderNumberGenerator`, `ModelAttributeMapper`, and `OrderItemNormalizer` (behaviour unchanged).

## [0.1.5] - 2026-06-21

### Added

- `OrderItemNormalizer` for resolving checkout item payloads into normalized order details.

## [0.1.4] - 2026-06-15

### Changed

- Removed Composer fixed `version` field (versioning is tag-driven).

## [0.1.3] - 2026-06-15

### Added

- Host integration documentation.

## [0.1.2] - 2026-05-11

### Added

- Host-configurable commerce column mapping (`commerce.columns.*`) via `ModelAttributeMapper`.

## [0.1.1] - 2026-05-10

### Added

- Host-configurable commerce model resolution (`commerce.models.*`).

## [0.1.0] - 2026-05-10

### Added

- Initial release: reusable product, order, order detail, invoice, and entitlement core with configurable models/tables/columns, `OrderLifecycleService`, `EntitlementService`, and `OrderNumberGenerator`.
