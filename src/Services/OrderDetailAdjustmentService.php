<?php

namespace Lalalili\CommerceCore\Services;

use Illuminate\Database\Eloquent\Model;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;

class OrderDetailAdjustmentService
{
    public function __construct(private readonly ModelAttributeMapper $attributes) {}

    /**
     * @return array<string, mixed>
     */
    public function discountAdjustmentLine(
        string $identifierKey = 'product_id',
        string $identifier = 'POS-1',
        string $title = '折扣金額',
        int $qty = 1,
        int $erpSize = 999,
    ): array {
        return [
            $identifierKey => $identifier,
            'product_title' => $title,
            'qty' => $qty,
            'product' => ['erp_size' => $erpSize],
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>|null
     */
    public function discountLine(mixed $discountAmount, array $line, string $amountKey = 'sales_price'): ?array
    {
        $amount = $this->positiveAmount($discountAmount);
        if ($amount === 0) {
            return null;
        }

        return $this->lineWithAmount($line, $amount * -1, $amountKey);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @param  array<string, mixed>  $line
     * @return list<array<string, mixed>>
     */
    public function appendDiscountLine(
        array $details,
        mixed $discountAmount,
        array $line,
        string $amountKey = 'sales_price',
    ): array {
        $discountLine = $this->discountLine($discountAmount, $line, $amountKey);
        if ($discountLine === null) {
            return $details;
        }

        $details[] = $discountLine;

        return $details;
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @param  array<string, mixed>  $discountLine
     * @param  array<string, mixed>|null  $shippingLine
     * @return list<array<string, mixed>>
     */
    public function appendAccountingLines(
        array $details,
        mixed $discountAmount,
        array $discountLine,
        ?array $shippingLine = null,
        mixed $shippingAmount = 0,
        string $amountKey = 'sales_price',
    ): array {
        $shipping = $this->positiveAmount($shippingAmount);
        if ($shippingLine !== null && $shipping > 0) {
            $details[] = $this->lineWithAmount($shippingLine, $shipping, $amountKey);
        }

        return $this->appendDiscountLine($details, $discountAmount, $discountLine, $amountKey);
    }

    /**
     * @param  array<string, mixed>  $discountLine
     * @param  array<string, mixed>|null  $shippingLine
     * @param  array{details_relation?: string, discount_amount_key?: string, amount_key?: string}  $options
     * @return list<array<string, mixed>>
     */
    public function orderAccountingDetails(
        Model $order,
        array $discountLine,
        ?array $shippingLine = null,
        mixed $shippingAmount = 0,
        array $options = [],
    ): array {
        $detailsRelation = $this->detailsRelation($options);
        $discountAmountKey = $options['discount_amount_key'] ?? 'total_discount_amt';
        $amountKey = $options['amount_key'] ?? 'sales_price';

        $order->loadMissing($detailsRelation);

        $details = [];

        foreach ($order->getRelationValue($detailsRelation) ?? [] as $detail) {
            if ($detail instanceof Model) {
                $details[] = $detail->toArray();
            }
        }

        return $this->appendAccountingLines(
            $details,
            $this->attributes->value($order, 'orders', $discountAmountKey, 0),
            $discountLine,
            $shippingLine,
            $shippingAmount,
            $amountKey,
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<int|string, list<array<string, mixed>>>
     */
    public function discountLinesByTaxBucket(
        mixed $discountAmount,
        mixed $taxableAmount,
        array $line,
        int|string $taxableKey = 1,
        int|string $taxFreeKey = 0,
        string $amountKey = 'sales_price',
    ): array {
        $discount = $this->positiveAmount($discountAmount);
        if ($discount === 0) {
            return [];
        }

        $taxable = $this->positiveAmount($taxableAmount);
        if ($taxable >= $discount) {
            return [
                $taxableKey => [$this->lineWithAmount($line, $discount * -1, $amountKey)],
            ];
        }

        $lines = [];
        if ($taxable > 0) {
            $lines[$taxableKey][] = $this->lineWithAmount($line, $taxable * -1, $amountKey);
        }

        $taxFreeAmount = $discount - $taxable;
        if ($taxFreeAmount > 0) {
            $lines[$taxFreeKey][] = $this->lineWithAmount($line, $taxFreeAmount * -1, $amountKey);
        }

        return $lines;
    }

    private function positiveAmount(mixed $value): int
    {
        return max(0, (int) round((float) $value));
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function lineWithAmount(array $line, int $amount, string $amountKey): array
    {
        $line[$amountKey] = $amount;

        return $line;
    }

    /**
     * @param  array{details_relation?: string}  $options
     */
    private function detailsRelation(array $options = []): string
    {
        $relation = $options['details_relation'] ?? config('commerce.relationships.order_details', 'details');

        return is_string($relation) && $relation !== '' ? $relation : 'details';
    }
}
