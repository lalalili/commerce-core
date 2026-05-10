<?php

use Lalalili\CommerceCore\Enums\InvoiceStatus;
use Lalalili\CommerceCore\Enums\OrderStatus;
use Lalalili\CommerceCore\Enums\PaymentStatus;
use Lalalili\CommerceCore\Models\InvoiceDonation;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Models\OrderDetail;
use Lalalili\CommerceCore\Models\OrderInvoice;
use Lalalili\CommerceCore\Models\PaymentLog;
use Lalalili\CommerceCore\Models\Product;
use Lalalili\CommerceCore\Models\ProductDetail;
use Lalalili\CommerceCore\Models\ProductUser;

return [
    'models' => [
        'product' => Product::class,
        'product_detail' => ProductDetail::class,
        'product_user' => ProductUser::class,
        'order' => Order::class,
        'order_detail' => OrderDetail::class,
        'payment_log' => PaymentLog::class,
        'order_invoice' => OrderInvoice::class,
        'invoice_donation' => InvoiceDonation::class,
    ],

    'tables' => [
        'products' => 'products',
        'product_details' => 'product_details',
        'product_user' => 'product_user',
        'orders' => 'orders',
        'order_details' => 'order_details',
        'payment_logs' => 'payment_logs',
        'order_invoices' => 'order_invoices',
        'invoice_donations' => 'invoice_donations',
    ],

    'relationships' => [
        'order_details' => 'details',
        'order_invoices' => 'invoices',
    ],

    'statuses' => [
        'order' => [
            'pending' => OrderStatus::Pending,
            'complete' => OrderStatus::Complete,
            'cancelled' => OrderStatus::Cancelled,
        ],
        'payment' => [
            'pending' => PaymentStatus::Pending,
            'complete' => PaymentStatus::Complete,
            'cancelled' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
        ],
        'invoice' => [
            'pending' => InvoiceStatus::Pending,
            'complete' => InvoiceStatus::Complete,
            'cancelled' => InvoiceStatus::Cancelled,
        ],
    ],
];
