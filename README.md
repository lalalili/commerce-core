# Commerce Core

Reusable product, order, invoice, payment log, and entitlement core for Laravel commerce applications.

`lalalili/commerce-core` provides a configurable commerce domain layer that other packages, such as `course-commerce` and `payment-ecpay`, can integrate with without depending on a host application's concrete models.

## Features

- Configurable product, order, detail, invoice, payment log, and entitlement models.
- Configurable table names and column mappings for host compatibility.
- Order creation, paid, refunded, cancel, and tax grouping lifecycle services.
- Entitlement grant/revoke support through product-user records.
- Host lifecycle hooks for project-specific side effects after paid, refunded, or cancelled transitions.
- Order number generation and attribute mapping helpers.

## Installation

If the host application uses Laravel Sail, prefix Composer and Artisan commands with `./vendor/bin/sail`.

```bash
composer require lalalili/commerce-core
php artisan vendor:publish --tag=commerce-core-config
php artisan vendor:publish --tag=commerce-core-migrations
php artisan migrate
```

For GitHub installs before a Packagist release:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lalalili/commerce-core.git"}
    ]
}
```

## Configuration

Publish `config/commerce.php` to customize:

- model classes
- table names
- relationship names
- entitlement behavior
- lifecycle hooks
- host column mappings
- order, payment, and invoice status mappings

This package is designed to work with existing host schemas by mapping logical commerce fields to host column names.

Example host mappings:

- `aitehub` keeps entitlements enabled and maps commerce product columns such as `number` to `prod_no`, with course packages depending on `commerce-core` through `course-commerce` and `payment-ecpay`.
- `cptw` maps legacy tables such as `order`, `order_detail`, `payment_log`, and `product`, disables `commerce.entitlements.enabled`, and keeps EPUB access grants in its host app services.

Host applications should prefer overriding `config/commerce.php` instead of subclassing package models or services for schema compatibility.

## Usage

Create an order:

```php
use Lalalili\CommerceCore\Services\OrderLifecycleService;

$order = app(OrderLifecycleService::class)->create(
    userId: $user->id,
    items: [
        ['product_id' => $product->id, 'qty' => 1],
    ],
);
```

Mark an order as paid and grant entitlements:

```php
app(OrderLifecycleService::class)->markPaid(
    orderNumber: $order->number,
    paymentStatusMessage: 'paid',
    paymentTime: now(),
    updatedBy: $user->id,
);
```

Cancel an order and revoke entitlements:

```php
app(OrderLifecycleService::class)->cancel($order->number, updatedBy: $user->id);
```

Mark an order as refunded without cancelling it:

```php
app(OrderLifecycleService::class)->markRefunded(
    orderNumber: $order->number,
    paymentStatusMessage: 'refunded',
    updatedBy: $user->id,
);
```

Register host lifecycle hooks for project-specific side effects:

```php
// config/commerce.php
'lifecycle' => [
    'hooks' => [
        App\Commerce\EbookEntitlementLifecycleHook::class,
    ],
],
```

Hooks must implement `Lalalili\CommerceCore\Contracts\OrderLifecycleHook`.

## Boundaries

- This package does not own payment gateway callbacks, checkout pages, or Filament resources.
- Payment adapters should call the lifecycle services after gateway verification.
- Host applications own authorization, checkout UX, tax policy, and gateway-specific credentials.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
