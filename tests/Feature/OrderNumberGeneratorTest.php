<?php

use Carbon\Carbon;
use Lalalili\CommerceCore\Models\Order;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;

afterEach(function (): void {
    Carbon::setTestNow();
});

/**
 * Rebuild the deterministic 9-char prefix the generator derives from the
 * frozen Asia/Taipei clock (ymd + hour/minute/second letters).
 */
function expectedOrderNumberPrefix(): string
{
    $now = now('Asia/Taipei');
    $alphabet = range('A', 'Z');

    return $now->format('ymd')
        .$alphabet[$now->hour % 26]
        .$alphabet[$now->minute % 26]
        .$alphabet[$now->second % 26];
}

it('generates a 10 character number prefixed with the Taipei date and time letters', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 3, 14, 9, 'Asia/Taipei'));

    $number = (new OrderNumberGenerator)->generate();

    expect($number)->toMatch('/^\d{6}[A-Z]{4}$/')
        ->and(substr($number, 0, 9))->toBe(expectedOrderNumberPrefix());
});

it('retries until it finds an unused number on collision', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 3, 14, 9, 'Asia/Taipei'));

    $prefix = expectedOrderNumberPrefix();

    // Occupy 25 of the 26 possible trailing letters, leaving only 'Q' free.
    foreach (range('A', 'Z') as $letter) {
        if ($letter === 'Q') {
            continue;
        }

        Order::query()->create(['number' => $prefix.$letter, 'user_id' => 1]);
    }

    expect((new OrderNumberGenerator)->generate())->toBe($prefix.'Q');
});

it('generates timestamp letter codes without checking order collisions', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 3, 14, 9, 'Asia/Taipei'));

    $number = (new OrderNumberGenerator)->generateTimestampLetterCode();

    expect($number)->toMatch('/^\d{6}[A-Z]{4}$/')
        ->and(substr($number, 0, 9))->toBe(expectedOrderNumberPrefix());
});

it('generates timestamp numeric codes with a fixed width id suffix', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 3, 14, 9, 'Asia/Taipei'));

    $number = (new OrderNumberGenerator)->generateTimestampNumericCode(1234567);

    expect($number)->toMatch('/^260622031409234567\d{2}$/');
});

it('allows timestamp numeric code widths to be customized', function (): void {
    Carbon::setTestNow(Carbon::create(2026, 6, 22, 3, 14, 9, 'Asia/Taipei'));

    $number = (new OrderNumberGenerator)->generateTimestampNumericCode(42, idDigits: 4, randomDigits: 3);

    expect($number)->toMatch('/^2606220314090042\d{3}$/');
});

it('rejects invalid timestamp numeric code widths', function (): void {
    expect(fn () => (new OrderNumberGenerator)->generateTimestampNumericCode(42, idDigits: 0))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => (new OrderNumberGenerator)->generateTimestampNumericCode(42, randomDigits: 0))
        ->toThrow(InvalidArgumentException::class);
});
