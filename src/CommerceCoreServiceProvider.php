<?php

namespace Lalalili\CommerceCore;

use Lalalili\CommerceCore\Services\CheckoutService;
use Lalalili\CommerceCore\Services\CheckoutSnapshotService;
use Lalalili\CommerceCore\Services\CouponCodeGenerationPolicy;
use Lalalili\CommerceCore\Services\CouponEligibilityCartDataFactory;
use Lalalili\CommerceCore\Services\EntitlementService;
use Lalalili\CommerceCore\Services\OrderInitialStatusResolver;
use Lalalili\CommerceCore\Services\OrderLifecycleHookDispatcher;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
use Lalalili\CommerceCore\Services\PaymentApplicationHookDispatcher;
use Lalalili\CommerceCore\Services\PaymentApplicationService;
use Lalalili\CommerceCore\Services\PaymentLogService;
use Lalalili\CommerceCore\Support\ModelAttributeMapper;
use Lalalili\CommerceCore\Support\OrderItemNormalizer;
use Lalalili\CommerceCore\Support\OrderNumberGenerator;
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
        $this->app->singleton(CheckoutService::class);
        $this->app->singleton(CheckoutSnapshotService::class);
        $this->app->singleton(CouponCodeGenerationPolicy::class);
        $this->app->singleton(CouponEligibilityCartDataFactory::class);
        $this->app->singleton(EntitlementService::class);
        $this->app->singleton(OrderInitialStatusResolver::class);
        $this->app->singleton(OrderLifecycleHookDispatcher::class);
        $this->app->singleton(OrderLifecycleService::class);
        $this->app->singleton(PaymentApplicationHookDispatcher::class);
        $this->app->singleton(PaymentApplicationService::class);
        $this->app->singleton(PaymentLogService::class);
    }
}
