# Commerce Core

Reusable product, order, invoice, payment log, and entitlement core for Laravel commerce applications.

`lalalili/commerce-core` provides a configurable commerce domain layer that other packages, such as `course-commerce` and `payment-ecpay`, can integrate with without depending on a host application's concrete models.

## Features

- Configurable product, order, detail, invoice, payment log, and entitlement models.
- Configurable table names and column mappings for host compatibility.
- Order creation, paid, cancel, and tax grouping lifecycle services.
- Entitlement grant/revoke support through product-user records.
- Order number generation and attribute mapping helpers.

## Installation

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
- host column mappings
- order, payment, and invoice status mappings

This package is designed to work with existing host schemas by mapping logical commerce fields to host column names.

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

## Boundaries

- This package does not own payment gateway callbacks, checkout pages, or Filament resources.
- Payment adapters should call the lifecycle services after gateway verification.
- Host applications own authorization, checkout UX, tax policy, and gateway-specific credentials.

## Tests

From the package directory:

```bash
./vendor/bin/pest
```
