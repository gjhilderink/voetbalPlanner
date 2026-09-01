<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Order;
use App\Support\Qr;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * De gekochte kaarten naar de koper.
 *
 * Niet ShouldQueue: op deze hosting draait geen queue-worker, dus een mail in
 * de wachtrij komt nooit aan. Alle mail hier gaat om dezelfde reden synchroon.
 *
 * De QR's gaan als bijlage mee en niet als data-URI in de tekst. Gmail en
 * Outlook strippen data:-afbeeldingen, en dan staat er een leeg vak waar het
 * kaartje hoort. De codes staan er in cijfers naast, en er is een link naar de
 * bestelpagina - drie wegen naar dezelfde code, want dit is wat iemand bij de
 * ingang nodig heeft.
 */
class TicketMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $primaryColor;
    public string $headerText;
    public string $footerText;
    public string $bestelUrl;

    public function __construct(public readonly Order $order)
    {
        $club = $order->club;

        $this->primaryColor = $club?->primary_color ?: '#5BA12F';
        $this->headerText   = $club?->name ?? config('app.name');
        $this->footerText   = $club?->email_footer_text ?? '';
        $this->bestelUrl    = url("/{$club?->slug}/ticketshop/klaar/{$order->public_token}");
    }

    public function envelope(): Envelope
    {
        $activiteit = $this->order->agendaItem?->title ?? 'je activiteit';

        return new Envelope(
            subject: 'Je kaarten voor ' . $activiteit,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.tickets');
    }

    /**
     * Eén PNG per kaart.
     *
     * De bestandsnaam bevat de code, zodat iemand die vier kaarten doorstuurt
     * ziet welke hij aan wie geeft.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->order->accessCodes
            ->map(fn ($code) => Attachment::fromData(
                fn () => Qr::pngBytes($code->code, 600),
                'kaart-' . $code->code . '.png',
            )->withMime('image/png'))
            ->all();
    }
}
