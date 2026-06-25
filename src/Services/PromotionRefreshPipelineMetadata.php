<?php

namespace Lalalili\CommerceCore\Services;

class PromotionRefreshPipelineMetadata
{
    /**
     * @param  array<string, mixed>  $refreshMetadata
     * @param  array<string, mixed>  $pricingTraceSummary
     * @return array{
     *     changed: bool,
     *     metadata: array{
     *         discount_pipeline: string,
     *         promotion_version: string,
     *         refresh_reason: string,
     *         duration_ms: int,
     *         promotion_refresh_signature: string,
     *         item_count: int,
     *         line_count: int,
     *         applied_count: int,
     *         skipped_count: int,
     *         selected_group_rebate_count: int,
     *         pricing_trace_summary: array<string, mixed>
     *     }
     * }
     */
    public function build(
        string $beforeHash,
        string $afterHash,
        bool $forceRefresh,
        array $refreshMetadata,
        int $durationMs,
        int $itemCount,
        array $pricingTraceSummary,
    ): array {
        $pipelineState = (string) ($refreshMetadata['discount_pipeline'] ?? 'refreshed');

        return [
            'changed' => $pipelineState === 'refreshed' && ! hash_equals($beforeHash, $afterHash),
            'metadata' => [
                'discount_pipeline' => $pipelineState,
                'promotion_version' => (string) ($refreshMetadata['promotion_version'] ?? ''),
                'refresh_reason' => (string) ($refreshMetadata['refresh_reason'] ?? ($forceRefresh ? 'forced' : 'signature_changed')),
                'duration_ms' => $durationMs,
                'promotion_refresh_signature' => (string) ($refreshMetadata['promotion_refresh_signature'] ?? ''),
                'item_count' => $itemCount,
                'line_count' => (int) ($refreshMetadata['line_count'] ?? 0),
                'applied_count' => (int) ($refreshMetadata['applied_count'] ?? 0),
                'skipped_count' => (int) ($refreshMetadata['skipped_count'] ?? 0),
                'selected_group_rebate_count' => (int) ($refreshMetadata['selected_group_rebate_count'] ?? 0),
                'pricing_trace_summary' => $pricingTraceSummary,
            ],
        ];
    }
}
