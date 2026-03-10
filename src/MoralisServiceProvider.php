<?php

namespace Locpx\MoralisTracker;

use Illuminate\Support\ServiceProvider;
use Locpx\MoralisTracker\Console\Commands\AddTrackedAddress;
use Locpx\MoralisTracker\Console\Commands\SyncTransactions;
use Locpx\MoralisTracker\Services\TransactionSyncService;

class MoralisServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/moralis.php',
            'moralis'
        );

        $this->app->singleton(MoralisClient::class, fn() => new MoralisClient());

        $this->app->singleton(TransactionSyncService::class, function ($app) {
            return new TransactionSyncService($app->make(MoralisClient::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
            $this->registerCommands();
        }

        $this->seedAddressesFromConfig();
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/moralis.php' => config_path('moralis.php'),
        ], 'moralis-config');
    }

    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'moralis-migrations');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function registerCommands(): void
    {
        $this->commands([
            SyncTransactions::class,
            AddTrackedAddress::class,
        ]);
    }

    protected function seedAddressesFromConfig(): void
    {
        $addresses    = config('moralis.addresses', []);
        $defaultChain = config('moralis.default_chain', 'bsc');

        if (empty($addresses)) {
            return;
        }

        try {
            foreach ($addresses as $address) {
                $address = strtolower(trim($address));
                if (!$address) {
                    continue;
                }

                \Locpx\MoralisTracker\Models\TrackedAddress::firstOrCreate(
                    ['address' => $address, 'chain' => $defaultChain],
                    ['is_active' => true]
                );
            }
        } catch (\Throwable) {
            // Silently skip if DB not ready (e.g. during install before migration)
        }
    }
}
