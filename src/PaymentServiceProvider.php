<?php

namespace Softscholar\Payment;

use Illuminate\Support\ServiceProvider;
use Softscholar\Payment\PaymentManager;

class PaymentServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/spayment.php' => config_path('spayment.php'),
        ], 'config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/spayment.php', 'spayment');

        $this->app->singleton(PaymentManager::class, fn () => new PaymentManager);

        // Optional facade alias
        $this->app->alias(PaymentManager::class, 'spayment');
    }
}
