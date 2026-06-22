<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Auth\Notifications\ResetPassword as BaseNotification;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseNotification
{
    public string $url;

    protected function resetUrl($notifiable): string
    {
        return $this->url;
    }

    public function toMail($notifiable): MailMessage
    {
        $club         = $notifiable->club ?? null;
        $clubId       = $club?->id;
        $primaryColor = $club?->primary_color ?? '#1e3a5f';

        $subject    = Setting::get('reset_email_subject',     'Wachtwoord opnieuw instellen', $clubId)
            ?: 'Wachtwoord opnieuw instellen';
        $headerText = Setting::get('reset_email_header_text', null, $clubId)
            ?: ($club?->name ?? config('app.name'));
        $introText  = Setting::get('reset_email_intro_text',  null, $clubId);
        $buttonText = Setting::get('reset_email_button_text', null, $clubId)
            ?: 'Wachtwoord opnieuw instellen';
        $footerText = Setting::get('reset_email_footer_text', null, $clubId)
            ?: $club?->email_footer_text;

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.password-reset', [
                'primaryColor'  => $primaryColor,
                'headerText'    => $headerText,
                'recipientName' => $notifiable->name,
                'introText'     => $introText,
                'buttonText'    => $buttonText,
                'footerText'    => $footerText,
                'resetUrl'      => $this->resetUrl($notifiable),
            ]);
    }
}
