<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Contracts\Pagos\PasarelaPago;
use App\Integrations\MercadoPago\MercadoPagoGateway;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            PasarelaPago::class,
            MercadoPagoGateway::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
