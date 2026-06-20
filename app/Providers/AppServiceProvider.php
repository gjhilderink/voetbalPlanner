<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use App\Services\SportlinkMcpService;
use Illuminate\Support\Facades\Config;
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
        app()->setLocale('nl');

        $this->bootSmtpFromSettings();

        // Some shared hosting servers ship libcurl without newer TLS constants.
        // Define them with their standard integer values so Guzzle doesn't crash.
        // CURL TLS constants may be missing on some shared hosting libcurl builds.
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6);
        }
        if (!defined('CURL_SSLVERSION_TLSv1_3')) {
            define('CURL_SSLVERSION_TLSv1_3', 7);
        }
    }

    private function bootSmtpFromSettings(): void
    {
        try {
            // Only override if the settings table exists and a host is configured.
            $host = Setting::get('smtp_host', null, null);
            if (!$host) return;

            $port       = Setting::get('smtp_port', '587', null);
            $encryption = Setting::get('smtp_encryption', 'tls', null);
            $username   = Setting::get('smtp_username', null, null);
            $password   = Setting::get('smtp_password', null, null);
            $fromAddr   = Setting::get('smtp_from_address', null, null);
            $fromName   = Setting::get('smtp_from_name', config('mail.from.name'), null);

            Config::set('mail.mailers.smtp.host',       $host);
            Config::set('mail.mailers.smtp.port',       (int) $port);
            Config::set('mail.mailers.smtp.encryption', $encryption ?: null);
            Config::set('mail.mailers.smtp.username',   $username);
            Config::set('mail.mailers.smtp.password',   $password);
            Config::set('mail.default', 'smtp');

            if ($fromAddr) {
                Config::set('mail.from.address', $fromAddr);
                Config::set('mail.from.name',    $fromName);
            }
        } catch (\Throwable) {
            // Database not yet available (e.g. during migrations) — use .env defaults.
        }
    }
}
