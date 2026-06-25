<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Lalalili\CommerceCore\Services\InvoiceIssueSchedulerService;

enum InvoiceSchedulerTestStatus: int
{
    case PENDING = 0;
    case COMPLETE = 1;
}

class InvoiceSchedulerTestOrder extends Model
{
    protected $table = 'invoice_scheduler_test_orders';

    protected $guarded = [];

    /**
     * @return HasMany<InvoiceSchedulerTestInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceSchedulerTestInvoice::class, 'order_id');
    }
}

class InvoiceSchedulerTestInvoice extends Model
{
    protected $table = 'invoice_scheduler_test_invoices';

    protected $guarded = [];
}

beforeEach(function (): void {
    Schema::create('invoice_scheduler_test_orders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedTinyInteger('status');
        $table->timestamp('created_at')->nullable();
        $table->timestamp('updated_at')->nullable();
        $table->timestamp('deleted_at')->nullable();
    });

    Schema::create('invoice_scheduler_test_invoices', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('order_id');
        $table->unsignedTinyInteger('status');
        $table->timestamps();
    });
});

it('builds an eligible invoice issue order query', function (): void {
    $now = CarbonImmutable::parse('2026-06-25 12:00:00');

    $eligible = InvoiceSchedulerTestOrder::query()->create([
        'status' => InvoiceSchedulerTestStatus::COMPLETE,
        'created_at' => $now->subDays(7),
    ]);
    $recent = InvoiceSchedulerTestOrder::query()->create([
        'status' => InvoiceSchedulerTestStatus::COMPLETE,
        'created_at' => $now->subDays(2),
    ]);
    $tooOld = InvoiceSchedulerTestOrder::query()->create([
        'status' => InvoiceSchedulerTestStatus::COMPLETE,
        'created_at' => $now->subDays(61),
    ]);
    $pending = InvoiceSchedulerTestOrder::query()->create([
        'status' => InvoiceSchedulerTestStatus::PENDING,
        'created_at' => $now->subDays(7),
    ]);
    $issued = InvoiceSchedulerTestOrder::query()->create([
        'status' => InvoiceSchedulerTestStatus::COMPLETE,
        'created_at' => $now->subDays(7),
    ]);

    InvoiceSchedulerTestInvoice::query()->create([
        'order_id' => $issued->getKey(),
        'status' => InvoiceSchedulerTestStatus::COMPLETE,
    ]);

    $service = app(InvoiceIssueSchedulerService::class);
    $query = $service->eligibleOrderQuery(
        orderModel: InvoiceSchedulerTestOrder::class,
        completedOrderStatus: InvoiceSchedulerTestStatus::COMPLETE,
        completedInvoiceStatus: InvoiceSchedulerTestStatus::COMPLETE,
        invoiceRelation: 'invoices',
        days: 6,
        now: $now,
    );

    expect($service->pendingCount($query))->toBe(1)
        ->and($query->pluck('id')->all())->toBe([$eligible->getKey()])
        ->and($query->pluck('id')->all())->not->toContain($recent->getKey(), $tooOld->getKey(), $pending->getKey(), $issued->getKey());
});

it('dispatches eligible orders by chunks', function (): void {
    $now = CarbonImmutable::parse('2026-06-25 12:00:00');
    $orders = collect(range(1, 3))->map(fn (): InvoiceSchedulerTestOrder => InvoiceSchedulerTestOrder::query()->create([
        'status' => InvoiceSchedulerTestStatus::COMPLETE,
        'created_at' => $now->subDays(7),
    ]));

    $service = app(InvoiceIssueSchedulerService::class);
    $query = $service->eligibleOrderQuery(
        orderModel: InvoiceSchedulerTestOrder::class,
        completedOrderStatus: InvoiceSchedulerTestStatus::COMPLETE,
        completedInvoiceStatus: InvoiceSchedulerTestStatus::COMPLETE,
        invoiceRelation: 'invoices',
        days: 6,
        now: $now,
    );
    $dispatchedOrderIds = [];
    $chunkSizes = [];

    $dispatchedCount = $service->dispatchEligibleOrders(
        eligibleOrderQuery: $query,
        chunkSize: 2,
        dispatchOrder: function (int|string $orderId) use (&$dispatchedOrderIds): void {
            $dispatchedOrderIds[] = (int) $orderId;
        },
        chunkDispatched: function (Collection $orders) use (&$chunkSizes): void {
            $chunkSizes[] = $orders->count();
        },
    );

    expect($dispatchedCount)->toBe(3)
        ->and($dispatchedOrderIds)->toBe($orders->pluck('id')->all())
        ->and($chunkSizes)->toBe([2, 1]);
});

it('rejects invalid chunk sizes', function (): void {
    app(InvoiceIssueSchedulerService::class)->dispatchEligibleOrders(
        eligibleOrderQuery: InvoiceSchedulerTestOrder::query(),
        chunkSize: 0,
        dispatchOrder: static function (): void {},
    );
})->throws(InvalidArgumentException::class, 'Invoice issue scheduler chunk size must be greater than or equal to 1.');
