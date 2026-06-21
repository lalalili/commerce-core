<?php

namespace Lalalili\CommerceCore;

use Lalalili\CommerceCore\Services\EntitlementService;
use Lalalili\CommerceCore\Services\OrderLifecycleService;
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
        $this->app->singleton(EntitlementService::class);
        $this->app->singleton(OrderLifecycleService::class);
    }
}
