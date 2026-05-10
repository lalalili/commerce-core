<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create(config('commerce.tables.products', 'products'), function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('number')->nullable()->index();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->unsignedTinyInteger('type')->default(1)->index();
            $table->unsignedInteger('list_price')->default(0);
            $table->unsignedInteger('sales_price')->default(0);
            $table->unsignedTinyInteger('tax')->default(1);
            $table->boolean('active')->default(true)->index();
            $table->foreignId('company_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create(config('commerce.tables.product_details', 'product_details'), function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('product_id')->index();
            $table->text('intro')->nullable();
            $table->longText('description')->nullable();
            $table->timestamps();
        });

        Schema::create(config('commerce.tables.orders', 'orders'), function (Blueprint $table): void {
            $table->id();
            $table->string('number', 32)->unique();
            $table->foreignId('user_id')->index();
            $table->unsignedInteger('total_discount_amt')->default(0);
            $table->unsignedInteger('total_sales_price')->default(0);
            $table->unsignedTinyInteger('payment_type')->default(1);
            $table->unsignedTinyInteger('payment_status')->default(0)->index();
            $table->string('payment_status_message')->nullable();
            $table->dateTime('payment_time')->nullable();
            $table->unsignedTinyInteger('invoice_type')->default(1);
            $table->json('invoice_code')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->dateTime('cancel_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create(config('commerce.tables.order_details', 'order_details'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->index();
            $table->string('order_number', 32)->index();
            $table->ulid('product_id')->index();
            $table->unsignedTinyInteger('product_type')->nullable()->index();
            $table->foreignId('company_id')->nullable()->index();
            $table->string('title');
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedInteger('list_price')->default(0);
            $table->unsignedInteger('sales_price')->default(0);
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create(config('commerce.tables.product_user', 'product_user'), function (Blueprint $table): void {
            $table->id();
            $table->ulid('product_id')->index();
            $table->foreignId('user_id')->index();
            $table->string('order_number', 32)->nullable()->index();
            $table->unsignedTinyInteger('product_type')->nullable()->index();
            $table->json('settings')->nullable();
            $table->foreignId('created_by')->nullable()->index();
            $table->timestamp('created_at')->nullable();
            $table->unique(['product_id', 'user_id']);
        });

        Schema::create(config('commerce.tables.payment_logs', 'payment_logs'), function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->index();
            $table->json('response');
            $table->string('status_code', 32)->nullable()->index();
            $table->string('status_message')->nullable();
            $table->timestamps();
            $table->unique(['order_number', 'status_code']);
        });

        Schema::create(config('commerce.tables.order_invoices', 'order_invoices'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->foreignId('order_id')->index();
            $table->string('order_number', 32)->index();
            $table->unsignedInteger('total_sales_price')->default(0);
            $table->unsignedTinyInteger('type')->default(1);
            $table->string('number', 32)->nullable()->index();
            $table->unsignedTinyInteger('status')->default(0)->index();
            $table->dateTime('issued_at')->nullable();
            $table->foreignId('created_by')->nullable()->index();
            $table->foreignId('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create(config('commerce.tables.invoice_donations', 'invoice_donations'), function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50);
            $table->string('code', 20)->unique();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('commerce.tables.invoice_donations', 'invoice_donations'));
        Schema::dropIfExists(config('commerce.tables.order_invoices', 'order_invoices'));
        Schema::dropIfExists(config('commerce.tables.payment_logs', 'payment_logs'));
        Schema::dropIfExists(config('commerce.tables.product_user', 'product_user'));
        Schema::dropIfExists(config('commerce.tables.order_details', 'order_details'));
        Schema::dropIfExists(config('commerce.tables.orders', 'orders'));
        Schema::dropIfExists(config('commerce.tables.product_details', 'product_details'));
        Schema::dropIfExists(config('commerce.tables.products', 'products'));
    }
};
