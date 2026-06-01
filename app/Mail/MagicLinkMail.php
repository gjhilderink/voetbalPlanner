<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MagicLinkMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $loginUrl;

    public function __construct(public readonly string $token, public readonly string $recipientName)
    {
        $this->loginUrl = url('/magic/' . $token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Jouw inloglink voor ' . config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.magic-link',
        );
    }
}
