<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseNotification;

/**
 * Synchronous password reset notification — overrides Filament's queued version
 * so the email is sent immediately without a queue worker.
 */
class ResetPasswordNotification extends BaseNotification
{
    public string $url;

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }
}
