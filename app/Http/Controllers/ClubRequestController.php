<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ClubRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class ClubRequestController extends Controller
{
    public function create(): View
    {
        return view('club-request.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'club_name'           => ['required', 'string', 'max:255'],
            'contact_name'        => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255'],
            'phone'               => ['nullable', 'string', 'max:30'],
            'sportlink_username'  => ['required', 'string', 'max:255'],
            'sportlink_password'  => ['required', 'string', 'max:255'],
            'notes'               => ['nullable', 'string', 'max:2000'],
        ]);

        ClubRequest::create($validated);

        $notifyEmail = Setting::get('registration_notification_email', null, null);
        if ($notifyEmail) {
            $subjectTemplate = Setting::get('registration_notification_subject', '', null);
            $subject = $subjectTemplate
                ? str_replace('{club_naam}', $validated['club_name'], $subjectTemplate)
                : "Nieuwe clubaanvraag: {$validated['club_name']}";

            Mail::raw(
                "Nieuwe clubaanvraag ontvangen van: {$validated['club_name']}\n"
                . "Contactpersoon: {$validated['contact_name']}\n"
                . "E-mail: {$validated['email']}\n"
                . "Telefoon: " . ($validated['phone'] ?? '—') . "\n\n"
                . "Beheer aanvragen via het admin-paneel.",
                fn($msg) => $msg->to($notifyEmail)->subject($subject)
            );
        }

        return redirect()->route('club-request.success');
    }

    public function success(): View
    {
        return view('club-request.success');
    }
}
