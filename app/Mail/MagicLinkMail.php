<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Club;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $loginUrl;
    public string $primaryColor;
    public string $headerText;
    public string $introText;
    public string $footerText;
    public string $subjectLine;

    public function __construct(
        public readonly string $token,
        public readonly string $recipientName,
        ?Club $club = null,
    ) {
        $this->loginUrl     = url('/magic/' . $token);
        $this->primaryColor = $club?->primary_color ?? '#1e3a5f';
        $this->headerText   = $club?->email_header_text ?? config('app.name');
        $this->introText    = $club?->email_intro_text ?? '';
        $this->footerText   = $club?->email_footer_text ?? '';

        // Configureerbaar per club; leeg = standaard.
        // Ondersteunt placeholders {club_naam}, {ontvanger_naam}, {app_naam}.
        $rawSubject = trim($club?->email_subject ?? '');
        $template   = $rawSubject !== '' ? $rawSubject : 'Jouw inloglink voor {app_naam}';
        $this->subjectLine = $this->renderPlaceholders($template, $club);
    }

    /**
     * Vervangt placeholders in een tekst-template:
     *   {club_naam}      → naam van de club (of app-naam als fallback)
     *   {ontvanger_naam} → naam van de ontvanger
     *   {app_naam}       → APP_NAME uit config
     */
    private function renderPlaceholders(string $template, ?Club $club): string
    {
        return strtr($template, [
            '{club_naam}'      => $club?->name ?? config('app.name'),
            '{ontvanger_naam}' => $this->recipientName,
            '{app_naam}'       => config('app.name'),
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
        );
    }
}
