# Changelog

All notable changes to `lalalili/commerce-core` are documented in this file.

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
