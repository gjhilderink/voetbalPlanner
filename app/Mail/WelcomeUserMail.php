<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Club;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welkomstmail voor een pas aangemaakt account.
 *
 * Bevat de inlognaam en een link waarmee de ontvanger zelf een wachtwoord kiest.
 * Bewust geen wachtwoord in de mail: dat zou per post rondgaan en daarna in
 * ieders inbox blijven staan.
 *
 * Zelfde opzet als MagicLinkMail, inclusief de clubbranding en de placeholders
 * in het onderwerp.
 */
class WelcomeUserMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;
    public string $username;
    public string $primaryColor;
    public string $headerText;
    public string $introText;
    public string $footerText;
    public string $subjectLine;
    public string $clubName;

    public function __construct(
        User $user,
        public readonly string $setPasswordUrl,
        public readonly int $expireMinutes = 60,
        ?Club $club = null,
    ) {
        $club ??= $user->club;

        $this->recipientName = $user->name ?: $user->email;
        // De inlognaam is het e-mailadres. Dat staat in de mail omdat mensen na
        // een paar weken niet meer weten met welk adres het account is gemaakt —
        // zeker als ze er meerdere hebben.
        $this->username = $user->email;

        $this->clubName     = $club?->name ?? config('app.name');
        $this->primaryColor = $club?->primary_color ?? '#1e3a5f';
        $this->headerText   = $club?->email_header_text ?? $this->clubName;
        $this->introText    = $club?->email_intro_text ?? '';
        $this->footerText   = $club?->email_footer_text ?? '';

        $this->subjectLine = strtr('Welkom bij {club_naam}', [
            '{club_naam}'      => $this->clubName,
            '{ontvanger_naam}' => $this->recipientName,
            '{app_naam}'       => config('app.name'),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.welcome-user');
    }
}
