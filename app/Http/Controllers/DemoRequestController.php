<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\VerifiesRecaptcha;
use App\Models\DemoRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Vrijblijvend een demo aanvragen.
 *
 * Zo min mogelijk gevraagd: naam, club en e-mail. Wie alleen wil kijken laat
 * geen formulier van twaalf velden achter, en alles wat hier extra staat kan
 * ook in het gesprek zelf.
 */
class DemoRequestController extends Controller
{
    use VerifiesRecaptcha;

    public function create(): View
    {
        return view('demo-request.create', [
            'recaptchaEnabled' => $this->recaptchaIngeschakeld(),
            'recaptchaSiteKey' => $this->recaptchaSleutel(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($this->recaptchaIngeschakeld() && ! $this->recaptchaGoedgekeurd($request)) {
            return back()
                ->withInput()
                ->withErrors(['captcha' => 'Bevestig dat je geen robot bent en probeer het opnieuw.']);
        }

        $validated = $request->validate([
            'club_name'    => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:30'],
            'member_count' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $aanvraag = DemoRequest::create($validated);

        $this->meldAan($aanvraag);

        return redirect()->route('demo-request.success');
    }

    public function success(): View
    {
        return view('demo-request.success');
    }

    /**
     * Stuurt de melding naar het ingestelde adres.
     *
     * Valt terug op het adres voor clubaanvragen. Anders zou het instellen van
     * één los adres de voorwaarde zijn om überhaupt te horen dat er iemand
     * belangstelling heeft — en dat merk je pas als het al is misgegaan.
     */
    private function meldAan(DemoRequest $aanvraag): void
    {
        $adres = Setting::get('demo_request_notification_email', '', null)
            ?: Setting::get('registration_notification_email', '', null);

        if (! $adres) {
            return;
        }

        $sjabloon = (string) Setting::get('demo_request_notification_subject', '', null);
        $onderwerp = $sjabloon !== ''
            ? str_replace('{club_naam}', $aanvraag->club_name, $sjabloon)
            : "Demo-aanvraag: {$aanvraag->club_name}";

        $regels = [
            "Vrijblijvende demo-aanvraag van: {$aanvraag->club_name}",
            "Contactpersoon: {$aanvraag->contact_name}",
            "E-mail: {$aanvraag->email}",
            'Telefoon: ' . ($aanvraag->phone ?: '—'),
            'Aantal leden: ' . ($aanvraag->member_count !== null ? (string) $aanvraag->member_count : 'niet opgegeven'),
        ];

        if ($aanvraag->notes) {
            $regels[] = '';
            $regels[] = 'Opmerkingen:';
            $regels[] = $aanvraag->notes;
        }

        $regels[] = '';
        $regels[] = 'Beheer aanvragen via het admin-paneel.';

        Mail::raw(
            implode("\n", $regels),
            // Antwoorden gaat rechtstreeks naar de aanvrager; dat scheelt het
            // adres overtikken uit een melding die je toch al openhebt.
            fn ($msg) => $msg->to($adres)
                ->replyTo($aanvraag->email, $aanvraag->contact_name)
                ->subject($onderwerp),
        );
    }
}
