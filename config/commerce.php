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

    'entitlements' => [
        'enabled' => true,
    ],

    'lifecycle' => [
        'hooks' => [],
    ],

    'columns' => [
        'products' => [
            'number' => 'number',
            'title' => 'title',
            'subtitle' => 'subtitle',
            'type' => 'type',
            'list_price' => 'list_price',
            'sales_price' => 'sales_price',
            'tax' => 'tax',
            'active' => 'active',
            'company_id' => 'company_id',
        ],
        'orders' => [
            'number' => 'number',
            'user_id' => 'user_id',
            'total_discount_amt' => 'total_discount_amt',
            'total_sales_price' => 'total_sales_price',
            'payment_type' => 'payment_type',
            'payment_status' => 'payment_status',
            'payment_status_message' => 'payment_status_message',
            'payment_time' => 'payment_time',
            'payment_reconciled_at' => 'payment_reconciled_at',
            'invoice_type' => 'invoice_type',
            'invoice_code' => 'invoice_code',
            'notes' => 'notes',
            'status' => 'status',
            'created_by' => 'created_by',
            'updated_by' => 'updated_by',
            'cancel_at' => 'cancel_at',
        ],
        'order_details' => [
            'order_id' => 'order_id',
            'order_number' => 'order_number',
            'product_id' => 'product_id',
            'product_number' => null,
            'title' => 'title',
            'product_type' => 'product_type',
            'company_id' => 'company_id',
            'qty' => 'qty',
            'list_price' => 'list_price',
            'sales_price' => 'sales_price',
            'status' => 'status',
            'created_by' => 'created_by',
            'updated_by' => 'updated_by',
        ],
        'product_user' => [
            'product_id' => 'product_id',
            'product_number' => null,
            'user_id' => 'user_id',
            'order_number' => 'order_number',
            'product_type' => 'product_type',
            'settings' => 'settings',
            'created_by' => 'created_by',
            'created_at' => 'created_at',
        ],
        'order_invoices' => [
            'status' => 'status',
            'updated_by' => 'updated_by',
        ],
    ],

    'statuses' => [
        'order' => [
            'pending' => OrderStatus::Pending,
            'paid' => OrderStatus::Complete,
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
