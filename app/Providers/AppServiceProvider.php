<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\SportlinkMcpService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SportlinkMcpService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Some shared hosting servers ship libcurl without newer TLS constants.
        // Define them with their standard integer values so Guzzle doesn't crash.
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        if (!defined('CURL_SSLVERSION_TLSv1_3')) {
            define('CURL_SSLVERSION_TLSv1_3', 7);
        }
    }
}
