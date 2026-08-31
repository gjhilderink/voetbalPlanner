<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessCode;
use App\Models\AccessEntry;
use App\Models\AgendaItem;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Toegangscontrole bij de ingang.
 *
 * Twee soorten codes komen hier binnen en zien er voor de scanner hetzelfde
 * uit: een uitgedeelde code met een eigen teller, en het lidnummer van een lid
 * bij een activiteit die op "gratis voor leden" staat.
 */
class AccessController extends Controller
{
    /** Wie er mag scannen. Coach of commissie is niet genoeg; dit is een eigen rol. */
    private const ROLLEN = ['super_admin', 'club_admin', 'toegang'];

    /**
     * GET /v1/access/events
     *
     * De activiteiten die te controleren zijn: alles met codes, plus alles wat
     * op gratis-voor-leden staat. Een activiteit zonder allebei valt af - daar
     * is niets te scannen.
     */
    public function events(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $user   = $request->user();
        $clubId = $user->club_id;

        $items = AgendaItem::query()
            ->where('club_id', $clubId)
            ->where(fn ($q) => $q
                ->where('free_for_members', true)
                ->orWhereExists(fn ($sub) => $sub
                    ->selectRaw('1')
                    ->from('access_codes')
                    ->whereColumn('access_codes.agenda_item_id', 'agenda_items.id')))
            // Wat al een week voorbij is hoeft niet meer in de keuzelijst; de
            // deur staat dan allang dicht.
            ->where('starts_at', '>=', now()->subWeek())
            ->orderBy('starts_at')
            ->withCount([
                'accessCodes',
                'accessEntries',
            ])
            ->get();

        return response()->json($items->map(fn (AgendaItem $item) => [
            'id'             => (string) $item->id,
            'title'          => (string) $item->title,
            'dateLabel'      => $item->starts_at?->translatedFormat('D j M H:i') ?? '',
            'location'       => (string) ($item->location ?? ''),
            'freeForMembers' => $item->free_for_members ? 'true' : 'false',
            'codeCount'      => (string) $item->access_codes_count,
            'entryCount'     => (string) $item->access_entries_count,
        ])->values());
    }

    /**
     * POST /v1/access/scan?event_id=&code=
     *
     * Geeft altijd HTTP 200, ook bij een ongeldige code. De app moet het
     * verschil kunnen zien tussen "deze code deugt niet" en "het netwerk
     * hapert", en dat lukt niet als allebei een foutcode opleveren.
     */
    public function scan(Request $request): JsonResponse
    {
        $this->authorizeRole();

        $validated = $request->validate([
            'event_id' => ['required', 'uuid'],
            'code'     => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();
        $code = trim($validated['code']);

        $item = AgendaItem::query()
            ->where('club_id', $user->club_id)
            ->find($validated['event_id']);

        if (! $item) {
            return $this->uitslag('unknown', 'Deze activiteit bestaat niet.');
        }

        // Alles in één transactie met een slot op de code: twee mensen aan de
        // deur die tegelijk dezelfde code scannen mogen niet allebei groen
        // krijgen.
        return DB::transaction(function () use ($item, $code, $user) {
            $toegangscode = AccessCode::query()
                ->where('agenda_item_id', $item->id)
                ->where('code', $code)
                ->lockForUpdate()
                ->first();

            if ($toegangscode) {
                return $this->scanCode($toegangscode, $item, $user);
            }

            // Geen uitgedeelde code. Bij een activiteit die gratis is voor
            // leden mag het ook een lidnummer zijn.
            if ($item->free_for_members) {
                return $this->scanLidnummer($item, $code, $user);
            }

            return $this->uitslag('unknown', 'Deze code hoort niet bij deze activiteit.');
        });
    }

    /** Een uitgedeelde code: teller ophogen zolang er ruimte is. */
    private function scanCode(AccessCode $code, AgendaItem $item, $user): JsonResponse
    {
        if (! $code->is_active) {
            return $this->uitslag('inactive', 'Deze code is ingetrokken.', $code->label);
        }

        if ($code->used_count >= $code->max_uses) {
            return $this->uitslag(
                'used',
                $code->max_uses === 1
                    ? 'Deze code is al gebruikt.'
                    : "Deze code is al {$code->used_count} keer gebruikt.",
                $code->label,
                $code->used_count,
                $code->max_uses,
            );
        }

        $code->increment('used_count');

        AccessEntry::create([
            'agenda_item_id' => $item->id,
            'access_code_id' => $code->id,
            'user_id'        => $user->id,
            'entered_at'     => now(),
        ]);

        $over = $code->max_uses - $code->used_count;

        return $this->uitslag(
            'ok',
            $code->max_uses === 1
                ? 'Welkom.'
                : "Welkom. Nog {$over} keer te gebruiken.",
            $code->label,
            $code->used_count,
            $code->max_uses,
        );
    }

    /**
     * Het lidnummer van een clublid, bij een activiteit die gratis is voor
     * leden. Eén keer per activiteit; dat wordt afgedwongen door de unieke
     * sleutel op access_entries, niet alleen door de controle hieronder.
     */
    private function scanLidnummer(AgendaItem $item, string $code, $user): JsonResponse
    {
        $lid = Member::query()
            ->where('club_id', $item->club_id)
            ->where('external_id', $code)
            ->where('is_active', true)
            ->first();

        if (! $lid) {
            return $this->uitslag('unknown', 'Geen geldige code of lidnummer.');
        }

        $alBinnen = AccessEntry::query()
            ->where('agenda_item_id', $item->id)
            ->where('member_id', $lid->id)
            ->exists();

        if ($alBinnen) {
            return $this->uitslag('used', 'Dit lid is al binnen.', $lid->name);
        }

        AccessEntry::create([
            'agenda_item_id' => $item->id,
            'member_id'      => $lid->id,
            'user_id'        => $user->id,
            'entered_at'     => now(),
        ]);

        return $this->uitslag('ok', 'Welkom.', $lid->name);
    }

    private function uitslag(
        string $status,
        string $message,
        ?string $label = null,
        ?int $used = null,
        ?int $max = null,
    ): JsonResponse {
        // Alles als tekst: de app-struct typeert deze velden zo, en een null
        // wordt daar de letterlijke tekst "null".
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'label'   => (string) ($label ?? ''),
            'used'    => (string) ($used ?? ''),
            'max'     => (string) ($max ?? ''),
        ]);
    }

    /**
     * Rol én module. De module staat per club aan of uit; is hij uit, dan is
     * er niets te scannen en hoort de app hier ook niet te komen.
     */
    private function authorizeRole(): void
    {
        $user = request()->user();

        if (! $user?->hasAnyRole(self::ROLLEN)) {
            abort(403, 'Geen toegang.');
        }

        if (! $user->club?->access_enabled) {
            abort(403, 'Toegangscontrole staat niet aan voor deze club.');
        }
    }
}
