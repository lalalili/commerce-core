<?php

namespace Lalalili\CommerceCore\Services;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class InvoiceIssueSchedulerService
{
    /**
     * @param  class-string<Model>  $orderModel
     * @return Builder<Model>
     */
    public function eligibleOrderQuery(
        string $orderModel,
        mixed $completedOrderStatus,
        mixed $completedInvoiceStatus,
        string $invoiceRelation,
        int $days,
        int $lookbackDays = 60,
        string $statusColumn = 'status',
        string $createdAtColumn = 'created_at',
        ?string $deletedAtColumn = 'deleted_at',
        ?CarbonInterface $now = null,
        string $invoiceStatusColumn = 'status',
    ): Builder {
        $this->assertOrderModel($orderModel);

        $capturedAt = $now ?? now();
        $issueBefore = $capturedAt->copy()->subDays($days);
        $issueAfter = $capturedAt->copy()->subDays($lookbackDays);

        $query = $orderModel::query()
            ->where($statusColumn, '=', $this->enumValue($completedOrderStatus))
            ->where($createdAtColumn, '<', $issueBefore)
            ->where($createdAtColumn, '>=', $issueAfter)
            ->whereDoesntHave($invoiceRelation, function (Builder $query) use ($completedInvoiceStatus, $invoiceStatusColumn): void {
                $query->where($invoiceStatusColumn, $this->enumValue($completedInvoiceStatus));
            });

        if ($deletedAtColumn !== null) {
            $query->whereNull($deletedAtColumn);
        }

        return $query;
    }

    /**
     * @param  Builder<Model>  $eligibleOrderQuery
     */
    public function pendingCount(Builder $eligibleOrderQuery): int
    {
        return (int) (clone $eligibleOrderQuery)->count();
    }

    /**
     * @param  Builder<Model>  $eligibleOrderQuery
     * @param  callable(int|string, Model): void  $dispatchOrder
     * @param  callable(Collection<int, Model>): void|null  $chunkDispatched
     */
    public function dispatchEligibleOrders(
        Builder $eligibleOrderQuery,
        int $chunkSize,
        callable $dispatchOrder,
        ?callable $chunkDispatched = null,
        string $keyColumn = 'id',
    ): int {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('Invoice issue scheduler chunk size must be greater than or equal to 1.');
        }

        $dispatched = 0;

        $eligibleOrderQuery
            ->select($keyColumn)
            ->orderBy($keyColumn)
            ->chunkById($chunkSize, function (Collection $orders) use ($dispatchOrder, $chunkDispatched, $keyColumn, &$dispatched): void {
                if ($chunkDispatched !== null) {
                    $chunkDispatched($orders);
                }

                $orders->each(function (Model $order) use ($dispatchOrder, $keyColumn, &$dispatched): void {
                    $key = $order->getAttribute($keyColumn) ?? $order->getKey();

                    $dispatchOrder($key, $order);
                    $dispatched++;
                });
            }, $keyColumn);

        return $dispatched;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @param  class-string<Model>  $orderModel
     */
    private function assertOrderModel(string $orderModel): void
    {
        if (! is_subclass_of($orderModel, Model::class)) {
            throw new InvalidArgumentException('Order model must extend '.Model::class.'.');
        }
    }
}
