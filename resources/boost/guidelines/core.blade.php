## Commerce Core

`lalalili/commerce-core` provides reusable product, order, invoice, payment log, and entitlement primitives for Laravel commerce hosts.

### Host Mapping

- Configure host models in `config/commerce.php` under `models`.
- Configure legacy or custom table names under `tables`.
- Configure logical-to-physical columns under `columns`; nullable mappings disable writes for unavailable host columns.
- Configure order/detail/invoice relationship names under `relationships`.
- Configure host enum/status values under `statuses`.

### Order Lifecycle

- Use `Lalalili\CommerceCore\Services\OrderLifecycleService` to create, mark paid, and cancel commerce orders.
- Pass checkout items with `product_id`, optional `qty`, `title`, `list_price`, `sales_price`, `product_type`, and `company_id`.
- Keep payment gateway payload/callback behavior in payment packages or the host app.
- Use `commerce.entitlements.enabled` to control whether paid orders grant product access.
