<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class GuardianLink extends Model
{
    use HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'club_id',
        'guardian_member_id',
        'child_member_id',
        'status',
        'request_token',
        'resolved_by_member_id',
        'resolved_at',
        'revoked_by_member_id',
        'revoked_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
            'revoked_at'  => 'datetime',
            'expires_at'  => 'datetime',
        ];
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /** Openstaande (niet-verlopen) verzoeken. */
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
                     ->where('expires_at', '>', now());
    }

    /** Goedgekeurde koppelingen. */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    // ── Relaties ───────────────────────────────────────────────────────────────

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'guardian_member_id');
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'child_member_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'resolved_by_member_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'revoked_by_member_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    public static function generateToken(): string
    {
        return Str::random(64);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** Mag dit verzoek nog geannuleerd/ingetrokken worden? */
    public function isRevocable(): bool
    {
        return in_array($this->status, ['pending', 'approved']);
    }

    /**
     * Het verzoek afhandelen: goedkeuren of weigeren, en de ouder een melding
     * sturen.
     *
     * Op het model en niet in de controller, sinds een beheerder dit ook in de
     * portal kan doen: gebeurt hetzelfde op twee plekken, dan loopt de melding
     * aan de ouder vroeg of laat op één van de twee achter.
     *
     * @param  string       $status          'approved' of 'rejected'
     * @param  string|null  $doorMemberId    wie het afhandelde; leeg bij een
     *                                       beheerder zonder eigen ledenrecord.
     * @param  bool         $doorBeheerder   bepaalt alleen de tekst van de
     *                                       melding: "de club" in plaats van de
     *                                       naam van het kind.
     */
    public function beslis(string $status, ?string $doorMemberId, bool $doorBeheerder = false): void
    {
        $this->update([
            'status'                => $status,
            'resolved_by_member_id' => $doorMemberId,
            'resolved_at'           => now(),
        ]);

        $this->meldBeslissingAanOuder($status, $doorBeheerder);
    }

    /**
     * Meldt de ouder/verzorger dat er op het verzoek is gereageerd.
     *
     * Gaat naar het topic `user_<sanitize(email)>` waar de app zich al op
     * abonneert. Faalt dit, dan blijft het bij een logregel: de beslissing is
     * verwerkt en dat mag niet stukgaan op een push.
     */
    private function meldBeslissingAanOuder(string $status, bool $doorBeheerder = false): void
    {
        try {
            $email = $this->guardian?->email;
            if (! $email) {
                return;
            }

            $kind = $this->child?->name ?: 'je kind';

            // Wie het besliste staat in de tekst. Een beheerder die goedkeurt
            // namens de club mag niet als het kind worden gepresenteerd: de
            // ouder zou denken dat zijn kind heeft gereageerd.
            $titel = $status === 'approved' ? 'Toegang goedgekeurd' : 'Verzoek geweigerd';
            $tekst = match (true) {
                $status === 'approved' && $doorBeheerder =>
                    "De club heeft je koppeling met {$kind} goedgekeurd. Je ziet nu de wedstrijden en trainingen in de app.",
                $status === 'approved' =>
                    "{$kind} heeft je toegang gegeven. Je ziet nu de wedstrijden en trainingen in de app.",
                $doorBeheerder =>
                    "De club heeft je verzoek om toegang tot de gegevens van {$kind} geweigerd.",
                default =>
                    "{$kind} heeft je verzoek om toegang geweigerd.",
            };

            app(\App\Services\FcmService::class)->sendToTopic(
                'user_' . \App\Services\FcmService::sanitizeTopicEmail($email),
                $titel,
                $tekst,
                ['type' => 'guardian', 'status' => $status],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[Guardian] push naar ouder mislukt', [
                'link'  => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
