<?php

use Lalalili\CommerceCore\Services\CartItemAttributeNormalizer;
use Lalalili\CommerceCore\Services\CartPromotionLineResolver;
use Lalalili\CommerceCore\Services\CartPromotionRefreshInputFactoryService;
use Lalalili\CommerceCore\Services\CheckoutCartCompletionService;
use Lalalili\CommerceCore\Services\CheckoutOrderBuilderService;
use Lalalili\CommerceCore\Services\CheckoutOrderDataFactory;
use Lalalili\CommerceCore\Services\CheckoutService;
use Lalalili\CommerceCore\Services\CheckoutSnapshotService;
use Lalalili\CommerceCore\Services\CouponCartPricingTraceService;
use Lalalili\CommerceCore\Services\CouponCheckoutAdapterService;
use Lalalili\CommerceCore\Services\CouponCodeGenerationPolicy;
use Lalalili\CommerceCore\Services\CouponDataPayloadResolver;
use Lalalili\CommerceCore\Services\CouponEligibilityCartDataFactory;
use Lalalili\CommerceCore\Services\CouponInventoryService;
use Lalalili\CommerceCore\Services\CouponPricingTraceContextService;
use Lalalili\CommerceCore\Services\CouponPricingTraceEntryFactory;
use Lalalili\CommerceCore\Services\CouponPricingTraceService;
use Lalalili\CommerceCore\Services\CouponTracePayloadResolver;
use Lalalili\CommerceCore\Services\EntitlementService;
use Lalalili\CommerceCore\Services\InvoiceIssueSchedulerService;
use Lalalili\CommerceCore\Services\OrderDetailAdjustmentService;
use Lalalili\CommerceCore\Services\OrderInitialStatusResolver;
use Lalalili\CommerceCore\Services\OrderInvoiceTaxGroupService;
use Lalalili\CommerceCore\Services\OrderLifecycleHookDispatcher;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommerceCore\Services\PaymentApplicationHookDispatcher;
use Lalalili\CommerceCore\Services\PaymentApplicationService;
use Lalalili\CommerceCore\Services\PaymentLogService;
use Lalalili\CommerceCore\Services\PromotionRefreshPipelineMetadata;
use Lalalili\CommerceCore\Services\ScheduledCouponTemplatePayloadBuilder;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommerceCore\Support\OrderItemNormalizer;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;

it('registers reusable commerce services as singletons', function (string $abstract): void {
    expect(app()->isShared($abstract))->toBeTrue();
})->with([
    ModelAttributeMapper::class,
    OrderItemNormalizer::class,
    OrderNumberGenerator::class,
    CartItemAttributeNormalizer::class,
    CartPromotionLineResolver::class,
    CartPromotionRefreshInputFactoryService::class,
    CheckoutCartCompletionService::class,
    CheckoutOrderBuilderService::class,
    CheckoutOrderDataFactory::class,
    CheckoutService::class,
    CheckoutSnapshotService::class,
    CouponCartPricingTraceService::class,
    CouponCheckoutAdapterService::class,
    CouponCodeGenerationPolicy::class,
    CouponDataPayloadResolver::class,
    CouponEligibilityCartDataFactory::class,
    CouponInventoryService::class,
    CouponPricingTraceEntryFactory::class,
    CouponPricingTraceContextService::class,
    CouponPricingTraceService::class,
    CouponTracePayloadResolver::class,
    EntitlementService::class,
    InvoiceIssueSchedulerService::class,
    OrderDetailAdjustmentService::class,
    OrderInitialStatusResolver::class,
    OrderInvoiceTaxGroupService::class,
    OrderLifecycleHookDispatcher::class,
    OrderLifecycleService::class,
    PaymentApplicationHookDispatcher::class,
    PaymentApplicationService::class,
    PaymentLogService::class,
    PromotionRefreshPipelineMetadata::class,
    ScheduledCouponTemplatePayloadBuilder::class,
]);
