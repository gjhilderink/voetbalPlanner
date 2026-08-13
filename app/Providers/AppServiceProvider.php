<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\SportlinkMcpService;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SportlinkMcpService::class);

        // Filament's ResetPassword notification implements ShouldQueue, which means
        // it requires a queue worker. Bind our synchronous version so emails are
        // sent immediately, just like the magic link mail.
        $this->app->bind(FilamentResetPassword::class, ResetPasswordNotification::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        app()->setLocale('nl');

        $this->bootSmtpFromSettings();

        // Stempel last_login_at bij elke succesvolle login die via de auth-guard
        // gaat: het Filament admin-panel én de API-wachtwoordlogin (Auth::attempt).
        // De magic-link login maakt zelf een token aan (geen Login-event) en zet
        // last_login_at expliciet in AuthController::verifyMagicLink.
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

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
        } catch (\Throwable $e) {
            // Database nog niet beschikbaar (bv. tijdens migraties) — dan zijn de
            // .env-waarden de bedoeling en is dit geen fout.
            //
            // Maar ook een mislukte decrypt() van smtp_password komt hier terecht
            // (bv. na een APP_KEY-wissel). Dan valt de mailer stil terug op .env
            // en faalt versturen met '535 Incorrect authentication data', zónder
            // enig spoor. Vandaar deze regel: zonder logging is dat vrijwel niet
            // te vinden.
            \Log::warning('[SMTP] instellingen niet toegepast, .env-waarden blijven gelden', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
