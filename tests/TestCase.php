<?php

namespace Lalalili\CommerceCore\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lalalili\CommerceCore\CommerceCoreServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use RuntimeException;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [
            CommerceCoreServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    protected function beforeRefreshingDatabase(): void
    {
        $this->guardAgainstCachedConfigDuringTests();
        $this->ensureSafeTestingDatabase();
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function ensureSafeTestingDatabase(): void
    {
        $defaultConnection = (string) config('database.default');
        $testingDatabase = (string) config('database.connections.testing.database');

        if ($defaultConnection === 'testing' && $testingDatabase === ':memory:') {
            return;
        }

        throw new RuntimeException(
            "Unsafe package test database detected. Connection [{$defaultConnection}] with testing database [{$testingDatabase}] is not allowed."
        );
    }

    protected function guardAgainstCachedConfigDuringTests(): void
    {
        foreach ($this->candidateConfigCachePaths() as $cachedConfigPath) {
            if (! is_file($cachedConfigPath)) {
                continue;
            }

            throw new RuntimeException(
                "Detected cached config at [{$cachedConfigPath}]. Run ./vendor/bin/sail artisan optimize:clear before running tests."
            );
        }
    }

    /**
     * @return array<int, string>
     */
    protected function candidateConfigCachePaths(): array
    {
        $workingDirectory = getcwd() ?: dirname(__DIR__, 3);

        return [
            $workingDirectory.'/bootstrap/cache/config.php',
            $workingDirectory.'/bootstrap/cache/config.testing.php',
        ];
    }
}
