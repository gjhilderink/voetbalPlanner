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
use Illuminate\Support\Collection;

class ReleaseNoteMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var Collection<int,ReleaseNote> */
    public Collection $notes;
    public string $primaryColor;
    public string $headerText;
    public string $footerText;
    public string $subjectLine;

    /**
     * @param ReleaseNote|iterable<ReleaseNote> $notes Eén of meerdere release notes.
     */
    public function __construct($notes, ?Club $club = null)
    {
        $this->notes = collect($notes instanceof ReleaseNote ? [$notes] : $notes)
            ->filter()
            ->values();

        $this->primaryColor = $club?->primary_color ?? '#1e3a5f';
        $this->headerText   = $club?->email_header_text ?? config('app.name');
        $this->footerText   = $club?->email_footer_text ?? '';

        $count = $this->notes->count();
        $this->subjectLine = $count === 1
            ? 'Update: ' . ($this->notes->first()->title ?? config('app.name'))
            : 'Nieuw in de app: ' . $count . ' updates';
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
