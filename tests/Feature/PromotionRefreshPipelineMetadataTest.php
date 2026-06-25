<?php

use Lalalili\CommerceCore\Services\PromotionRefreshPipelineMetadata;

it('marks refreshed promotion pipeline metadata as changed when cart hash changes', function (): void {
    $payload = app(PromotionRefreshPipelineMetadata::class)->build(
        beforeHash: 'before',
        afterHash: 'after',
        forceRefresh: false,
        refreshMetadata: [
            'discount_pipeline' => 'refreshed',
            'promotion_version' => 'v10',
            'refresh_reason' => 'promotion_changed',
            'promotion_refresh_signature' => 'sig-1',
            'line_count' => '2',
            'applied_count' => '1',
            'skipped_count' => '0',
            'selected_group_rebate_count' => '1',
        ],
        durationMs: 25,
        itemCount: 3,
        pricingTraceSummary: ['promotion' => ['total' => 2]],
    );

    expect($payload['changed'])->toBeTrue()
        ->and($payload['metadata'])->toBe([
            'discount_pipeline' => 'refreshed',
            'promotion_version' => 'v10',
            'refresh_reason' => 'promotion_changed',
            'duration_ms' => 25,
            'promotion_refresh_signature' => 'sig-1',
            'item_count' => 3,
            'line_count' => 2,
            'applied_count' => 1,
            'skipped_count' => 0,
            'selected_group_rebate_count' => 1,
            'pricing_trace_summary' => ['promotion' => ['total' => 2]],
        ]);
});

it('does not mark skipped or unchanged promotion refreshes as changed', function (): void {
    $service = app(PromotionRefreshPipelineMetadata::class);

    $skipped = $service->build(
        beforeHash: 'before',
        afterHash: 'after',
        forceRefresh: false,
        refreshMetadata: ['discount_pipeline' => 'skipped'],
        durationMs: 1,
        itemCount: 1,
        pricingTraceSummary: ['promotion' => []],
    );

    $unchanged = $service->build(
        beforeHash: 'same',
        afterHash: 'same',
        forceRefresh: true,
        refreshMetadata: ['discount_pipeline' => 'refreshed'],
        durationMs: 1,
        itemCount: 1,
        pricingTraceSummary: ['promotion' => []],
    );

    expect($skipped['changed'])->toBeFalse()
        ->and($unchanged['changed'])->toBeFalse();
});

it('falls back to forced or signature changed refresh reasons', function (): void {
    $service = app(PromotionRefreshPipelineMetadata::class);

    $forced = $service->build('a', 'b', true, [], 0, 0, ['promotion' => []]);
    $signatureChanged = $service->build('a', 'b', false, [], 0, 0, ['promotion' => []]);

    expect($forced['metadata']['refresh_reason'])->toBe('forced')
        ->and($signatureChanged['metadata']['refresh_reason'])->toBe('signature_changed');
});
