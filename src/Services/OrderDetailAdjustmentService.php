<?php

namespace Lalalili\CommerceCore\Services;

class OrderDetailAdjustmentService
{
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
}
