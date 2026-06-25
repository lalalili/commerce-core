<?php

namespace Lalalili\CommerceCore;

use Lalalili\CommerceCore\Services\CartItemAttributeNormalizer;
use Lalalili\CommerceCore\Services\CartPromotionLineResolver;
use Lalalili\CommerceCore\Services\CartPromotionRefreshInputFactoryService;
use Lalalili\CommerceCore\Services\CheckoutCartCompletionService;
use Lalalili\CommerceCore\Services\CheckoutOrderBuilderService;
use Lalalili\CommerceCore\Services\CheckoutOrderDataFactory;
use Lalalili\CommerceCore\Services\CheckoutService;
use Lalalili\CommerceCore\Services\CheckoutSnapshotService;
use Lalalili\CommerceCore\Services\CouponCartConditionPayloadBuilder;
use Lalalili\CommerceCore\Services\CouponCartPricingTraceService;
use Lalalili\CommerceCore\Services\CouponCheckoutAdapterService;
use Lalalili\CommerceCore\Services\CouponCodeGenerationPolicy;
use Lalalili\CommerceCore\Services\CouponDataFactory;
use Lalalili\CommerceCore\Services\CouponDataPayloadResolver;
use Lalalili\CommerceCore\Services\CouponEligibilityCartDataFactory;
use Lalalili\CommerceCore\Services\CouponFormPayloadBuilder;
use Lalalili\CommerceCore\Services\CouponInventoryService;
use Lalalili\CommerceCore\Services\CouponPricingTraceContextService;
use Lalalili\CommerceCore\Services\CouponPricingTraceEntryFactory;
use Lalalili\CommerceCore\Services\CouponPricingTraceService;
use Lalalili\CommerceCore\Services\CouponReasonMessageService;
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
use Lalalili\CommerceCore\Services\PromotionContextPayloadBuilder;
use Lalalili\CommerceCore\Services\PromotionRefreshPipelineMetadata;
use Lalalili\CommerceCore\Services\ScheduledCouponTemplatePayloadBuilder;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommerceCore\Support\OrderItemNormalizer;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;
use Lalalili\CommerceCore\Support\QueryBuilderHelper;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CommerceCoreServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('commerce-core')
            ->hasConfigFile('commerce')
            ->hasMigrations([
                '2026_05_10_000001_create_commerce_core_tables',
            ]);
    }

    public function registeringPackage(): void
    {
        $this->app->singleton(ModelAttributeMapper::class);
        $this->app->singleton(OrderItemNormalizer::class);
        $this->app->singleton(OrderNumberGenerator::class);
        $this->app->singleton(QueryBuilderHelper::class);
        $this->app->singleton(CartItemAttributeNormalizer::class);
        $this->app->singleton(CartPromotionLineResolver::class);
        $this->app->singleton(CartPromotionRefreshInputFactoryService::class);
        $this->app->singleton(CheckoutCartCompletionService::class);
        $this->app->singleton(CheckoutOrderBuilderService::class);
        $this->app->singleton(CheckoutOrderDataFactory::class);
        $this->app->singleton(CheckoutService::class);
        $this->app->singleton(CheckoutSnapshotService::class);
        $this->app->singleton(CouponCartConditionPayloadBuilder::class);
        $this->app->singleton(CouponCartPricingTraceService::class);
        $this->app->singleton(CouponCheckoutAdapterService::class);
        $this->app->singleton(CouponCodeGenerationPolicy::class);
        $this->app->singleton(CouponDataFactory::class);
        $this->app->singleton(CouponDataPayloadResolver::class);
        $this->app->singleton(CouponEligibilityCartDataFactory::class);
        $this->app->singleton(CouponFormPayloadBuilder::class);
        $this->app->singleton(CouponInventoryService::class);
        $this->app->singleton(CouponPricingTraceEntryFactory::class);
        $this->app->singleton(CouponPricingTraceContextService::class);
        $this->app->singleton(CouponPricingTraceService::class);
        $this->app->singleton(CouponReasonMessageService::class);
        $this->app->singleton(CouponTracePayloadResolver::class);
        $this->app->singleton(EntitlementService::class);
        $this->app->singleton(InvoiceIssueSchedulerService::class);
        $this->app->singleton(OrderDetailAdjustmentService::class);
        $this->app->singleton(OrderInitialStatusResolver::class);
        $this->app->singleton(OrderInvoiceTaxGroupService::class);
        $this->app->singleton(OrderLifecycleHookDispatcher::class);
        $this->app->singleton(OrderLifecycleService::class);
        $this->app->singleton(PaymentApplicationHookDispatcher::class);
        $this->app->singleton(PaymentApplicationService::class);
        $this->app->singleton(PaymentLogService::class);
        $this->app->singleton(PromotionContextPayloadBuilder::class);
        $this->app->singleton(PromotionRefreshPipelineMetadata::class);
        $this->app->singleton(ScheduledCouponTemplatePayloadBuilder::class);
    }
}
