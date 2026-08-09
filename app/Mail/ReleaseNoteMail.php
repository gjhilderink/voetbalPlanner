<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Club;
use App\Models\ReleaseNote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReleaseNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $primaryColor;
    public string $headerText;
    public string $footerText;
    public string $titleLine;
    public string $bodyHtml;
    public string $subjectLine;

    public function __construct(
        public readonly ReleaseNote $note,
        ?Club $club = null,
    ) {
        $this->primaryColor = $club?->primary_color ?? '#1e3a5f';
        $this->headerText   = $club?->email_header_text ?? config('app.name');
        $this->footerText   = $club?->email_footer_text ?? '';
        $this->titleLine    = $note->title;
        // body is RichEditor-HTML; kan leeg zijn.
        $this->bodyHtml     = trim((string) ($note->body ?? ''));
        $this->subjectLine  = 'Update: ' . $note->title;
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
            view: 'emails.release-note',
        );
    }
}
