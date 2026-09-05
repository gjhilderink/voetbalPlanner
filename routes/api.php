<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BugReportController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\GuestInvitationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\BarDutyController;
use App\Http\Controllers\Api\BrandingController;
use App\Http\Controllers\Api\DocumentationController;
use App\Http\Controllers\Api\AccessController;
use App\Http\Controllers\Api\TourController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\LineupController;
use App\Http\Controllers\Api\LiveMatchController;
use App\Http\Controllers\Api\MatchPhotoController;
use App\Http\Controllers\Api\MatchStatsController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\TrainingController;
use App\Http\Controllers\Api\AgendaController;
use App\Http\Controllers\Api\NewsItemController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\StaffGroupController;
use App\Http\Controllers\Api\StandingController;
use App\Http\Controllers\Api\SwapRequestController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamDocumentController;
use App\Http\Controllers\Api\ClothingController;
use App\Http\Controllers\Api\TeamLineupController;
use App\Http\Controllers\Api\TeamMoodController;
use App\Http\Controllers\Api\TeamStatsController;
use App\Http\Controllers\Api\TeamStatsDetailController;
use Illuminate\Support\Facades\Route;

// Globale catch-all voor OPTIONS preflight — moet ALS EERSTE staan zodat
// browsers altijd 204 + CORS headers krijgen, zelfs voor non-existent
// routes of routes achter auth:sanctum.
Route::options('{any?}', fn() => response('', 204))->where('any', '.*');

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/magic-link', [AuthController::class, 'sendMagicLink']);
    Route::post('/auth/verify-magic-link', [AuthController::class, 'verifyMagicLink']);

    // Guardian: ouder/verzorger self-registratie (publiek, throttled).
    // 10 per minuut: ruim genoeg voor legitieme herpogingen (validatiefouten,
    // testen) maar nog steeds anti-spam.
    Route::post('/guardian/self-register', [GuardianController::class, 'selfRegister'])
         ->middleware(['throttle:10,1']);

    // Terugmelding van Pay.nl na een betaling. Publiek, want Pay.nl logt
    // nergens in - de inhoud wordt niet geloofd, de stand wordt daarna bij
    // Pay.nl zelf opgehaald. Een ruimere limiet dan de rest: Pay.nl probeert
    // het bij twijfel meerdere keren.
    Route::post('/paynl/exchange', \App\Http\Controllers\Api\PayNlWebhookController::class)
         ->middleware(['throttle:120,1']);

    // Health
    Route::get('/health', fn() => response()->json(['status' => 'ok', 'timestamp' => now()]));

    // Diagnose: laat zien welke routes Laravel heeft gecached.
    // Voer uit: GET /api/v1/diagnose/routes — geen auth nodig.
    Route::get('/diagnose/routes', function () {
        $routes = collect(\Route::getRoutes())
            ->map(fn($r) => [
                'methods' => $r->methods(),
                'uri'     => $r->uri(),
            ])
            ->filter(fn($r) => str_starts_with($r['uri'], 'api/v1/'))
            ->values();
        return response()->json([
            'bug_reports_route_exists' => $routes->contains(
                fn($r) => str_contains($r['uri'], 'bug-reports')
            ),
            'bug_reports_table_exists' => \Schema::hasTable('bug_reports'),
            'storage_link_exists'      => is_link(public_path('storage')),
            'routes_count'             => $routes->count(),
            // app_env en app_debug stonden hier ook in. Dat vertelt een
            // buitenstaander of stacktraces aanstaan; niet iets om ongevraagd
            // prijs te geven. De waarden staan in de .env op de server.
        ]);
    });

    // Catch-all OPTIONS preflight zodat de browser nooit op een unmatched
    // OPTIONS request stuit. Stuurt 204 met de standaard CORS headers (toegevoegd
    // door HandleCorsApi middleware).
    Route::options('/{any?}', fn() => response('', 204))->where('any', '.*');
    Route::get('/sync/health', [SyncController::class, 'healthCheck']);

    // De tijdelijke /debug/echo, /debug/post-echo en /debug/token routes zijn
    // verwijderd. Ze stonden zonder auth open, en /debug/token gaf bij een
    // wíllekeurige tokenwaarde alsnog het bijbehorende user-id terug plus of dat
    // account actief of verwijderd was. Token-ids zijn oplopende getallen, dus
    // daarmee waren alle gebruikers-UUID's af te lopen zonder in te loggen.
    // Debug een tokenprobleem voortaan via de logs of tinker op de server.

    Route::middleware('auth:sanctum')->group(function () {
        // Auth
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Documentation
        Route::get('/documentation', [DocumentationController::class, 'index']);

        // Waar een in-app rondleiding moet beginnen. Geeft een lege matchId met
        // uitleg terug als er niets aan te wijzen valt; dat is een antwoord, geen fout.
        Route::get('/tour-target', [TourController::class, 'target']);

        // Toegangscontrole bij de ingang. Beide routes zijn gegrendeld op de rol
        // 'toegang' in de controller zelf; scannen geeft altijd HTTP 200, ook
        // bij een ongeldige code, zodat de app dat kan onderscheiden van een
        // netwerkfout.
        Route::get('/access/events', [AccessController::class, 'events']);
        Route::post('/access/scan', [AccessController::class, 'scan']);

        // Onboarding-slides (per club aanpasbaar, voor de rondleiding in de app)
        Route::get('/onboarding-slides', [OnboardingController::class, 'index']);

        // Branding
        Route::get('/branding', [BrandingController::class, 'show']);

        // Banners
        Route::get('/banners', [BannerController::class, 'index']);

        // Nieuws
        Route::get('/news', [NewsItemController::class, 'index']);
        Route::get('/news/{newsItem}', [NewsItemController::class, 'show']);

        // Verenigingsagenda. LET OP: /agenda/categories staat bewust vóór
        // /agenda/{agendaItem}, anders vangt de wildcard 'categories' op.
        Route::get('/agenda', [AgendaController::class, 'index']);
        Route::get('/agenda/categories', [AgendaController::class, 'categories']);
        Route::get('/agenda/{agendaItem}', [AgendaController::class, 'show']);
        Route::get('/agenda/{agendaItem}/deelnemers', [AgendaController::class, 'participants']);
        Route::get('/agenda/{agendaItem}/ics', [AgendaController::class, 'ics']);
        // Af-/aanmelden via POST (shared-host-veilig, zoals bij wedstrijden)
        Route::post('/agenda/{agendaItem}/aanmelden', [AgendaController::class, 'aanmelden']);
        Route::post('/agenda/{agendaItem}/afmelden', [AgendaController::class, 'afmelden']);

        // Teams
        Route::get('/teams', [TeamController::class, 'index']);
        Route::get('/teams/{team}', [TeamController::class, 'show']);
        Route::get('/teams/{team}/members', [TeamController::class, 'members']);
        // Documenten bij een elftal; clubbrede documenten gaan mee.
        Route::get('/teams/{team}/documents', [TeamDocumentController::class, 'index']);
        // Poulestand, live opgehaald bij de MCP-server (kort gecachet).
        Route::get('/teams/{team}/standing', [StandingController::class, 'show']);
        // Seizoenscijfers (team + jezelf) en teamsfeer voor het dashboard.
        Route::get('/teams/{team}/stats', [TeamStatsController::class, 'show']);
        // Doorklikscherm voor de coach: dezelfde cijfers, maar per speler.
        Route::get('/teams/{team}/team-stats', [TeamStatsDetailController::class, 'show']);
        Route::get('/teams/{team}/mood', [TeamMoodController::class, 'show']);
        Route::post('/teams/{team}/mood', [TeamMoodController::class, 'store']);

        // Members
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{member}', [MemberController::class, 'show']);

        // Matches
        Route::get('/matches', [MatchController::class, 'index']);
        Route::get('/matches/{match}', [MatchController::class, 'show']);
        Route::patch('/matches/{match}', [MatchController::class, 'update']);
        // Af-/aanmelden wedstrijd (POST i.p.v. DELETE/PATCH; shared-host-veilig)
        Route::get('/matches/{match}/afmeldingen', [MatchController::class, 'afmeldingen']);
        // Notitie bij een wedstrijd (coach/leider). POST i.p.v. PATCH, zie afmelden.
        Route::post('/matches/{match}/notitie', [MatchController::class, 'setNote']);
        // De aanvangstijd door de coach. Leeg = terug naar wat Sportlink zegt.
        Route::post('/matches/{match}/aanvangstijd', [MatchController::class, 'setAanvangstijd']);
        // Hele selectie met af-/aanmeldstatus; de coach zet ze hiermee om.
        Route::get('/matches/{match}/deelnemers', [MatchController::class, 'deelnemers']);
        Route::post('/matches/{match}/afmelden', [MatchController::class, 'afmelden']);
        Route::post('/matches/{match}/afgelasten', [MatchController::class, 'afgelasten']);
        Route::post('/matches/{match}/vrijgeven', [MatchController::class, 'vrijgeven']);
        Route::post('/matches/{match}/aanmelden', [MatchController::class, 'aanmelden']);

        // Vlagger (grensrechter) van een wedstrijd instellen (coach).
        Route::post('/matches/{match}/vlagger', [MatchController::class, 'setVlagger']);

        // Fruitheld van een wedstrijd instellen (coach).
        Route::post('/matches/{match}/fruithero', [MatchController::class, 'setFruitHero']);
        // Rijder aan/uit zetten. Per persoon en via POST: zie toggleRijder().
        Route::post('/matches/{match}/rijder', [MatchController::class, 'toggleRijder']);

        // Gastspeler uitnodigen voor een wedstrijd (coach) + de gast z'n uitnodigingen.
        Route::post('/matches/{match}/guest-invite', [GuestInvitationController::class, 'invite']);
        Route::post('/matches/{match}/guest-invite/remove', [GuestInvitationController::class, 'removeByMember']);
        Route::get('/matches/{match}/guests', [GuestInvitationController::class, 'guests']);
        Route::get('/guest-invite/teams', [GuestInvitationController::class, 'selectableTeams']);
        Route::get('/guest-invitations', [GuestInvitationController::class, 'myInvitations']);
        Route::delete('/guest-invitations/{invitation}/revoke', [GuestInvitationController::class, 'revoke']);

        // Trainingen (herhaal-schema's per team → berekende occurrences)
        Route::get('/trainings', [TrainingController::class, 'index']);
        Route::get('/trainings/{schedule}/{date}/deelnemers', [TrainingController::class, 'deelnemers']);
        Route::post('/trainings/{schedule}/{date}/afmelden', [TrainingController::class, 'afmelden']);
        Route::post('/trainings/{schedule}/{date}/aanmelden', [TrainingController::class, 'aanmelden']);
        // Afgelasten en weer vrijgeven; alleen voor wie het elftal beheert.
        Route::post('/trainings/{schedule}/{date}/afgelasten', [TrainingController::class, 'afgelasten']);
        Route::post('/trainings/{schedule}/{date}/vrijgeven', [TrainingController::class, 'vrijgeven']);

        // Lineups
        Route::get('/matches/{match}/lineup', [LineupController::class, 'show']);
        Route::post('/matches/{match}/lineup', [LineupController::class, 'store']);
        Route::post('/matches/{match}/lineup/player', [LineupController::class, 'addPlayer']);
        Route::post('/matches/{match}/lineup/player/remove', [LineupController::class, 'removePlayer']);
        // Het opstellingsbord: alles in één keer lezen en schrijven.
        Route::get('/matches/{match}/lineup/board', [LineupController::class, 'board']);
        Route::post('/matches/{match}/lineup/board', [LineupController::class, 'saveBoard']);
        Route::post('/matches/{match}/lineup/publish', [LineupController::class, 'publish']);
        // Standaardopstelling: onderhouden bij het elftal, inladen bij een
        // wedstrijd. POST voor het inladen, want het schrijft.
        Route::get('/teams/{team}/default-lineup', [TeamLineupController::class, 'show']);
        Route::post('/teams/{team}/default-lineup', [TeamLineupController::class, 'store']);
        Route::post('/matches/{match}/lineup/load-default', [TeamLineupController::class, 'load']);

        // Goals
        Route::get('/matches/{match}/goals', [GoalController::class, 'index']);
        Route::post('/matches/{match}/goals', [GoalController::class, 'store']);
        Route::post('/matches/{match}/goals/delete-last', [GoalController::class, 'destroyLast']);
        Route::delete('/matches/{match}/goals/{goal}', [GoalController::class, 'destroy']);

        // Live wedstrijdverslag. POST voor de schrijfacties, zoals overal hier:
        // de shared host blokkeert PATCH en DELETE.
        Route::get('/live', [LiveMatchController::class, 'mine']);
        Route::get('/matches/{match}/live', [LiveMatchController::class, 'show']);
        // Bewaard verslag, ook lang na afloop terug te kijken.
        Route::get('/matches/{match}/events', [LiveMatchController::class, 'events']);
        // Cijfers van een wedstrijd, afgeleid uit het live verslag.
        Route::get('/matches/{match}/stats', [MatchStatsController::class, 'show']);
        // Foto's bij een wedstrijd. POST voor verwijderen: de hosting
        // blokkeert DELETE, zoals overal in deze API.
        Route::get('/matches/{match}/photos', [MatchPhotoController::class, 'index']);
        Route::post('/matches/{match}/photos', [MatchPhotoController::class, 'store']);
        Route::post('/matches/{match}/photos/{photo}/delete', [MatchPhotoController::class, 'destroy']);

        // Alle verslagen van de eigen elftallen, met zoeken op tegenstander of team.
        Route::get('/match-reports', [LiveMatchController::class, 'reports']);
        Route::post('/matches/{match}/live/start', [LiveMatchController::class, 'start']);
        Route::post('/matches/{match}/live/event', [LiveMatchController::class, 'event']);
        Route::post('/matches/{match}/live/undo', [LiveMatchController::class, 'undo']);
        // Eén gebeurtenis corrigeren, ook na afloop. Undo pakt alleen de laatste.
        Route::post('/matches/{match}/live/event/{event}/verwijderen', [LiveMatchController::class, 'verwijderEvent']);
        Route::post('/matches/{match}/live/stop', [LiveMatchController::class, 'stop']);
        Route::post('/matches/{match}/live/delete', [LiveMatchController::class, 'destroy']);

        // Bar duties
        Route::apiResource('bar-duties', BarDutyController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::patch('bar-duties/{barDuty}/members', [BarDutyController::class, 'assignMembers']);
        // POST (i.p.v. PATCH) omdat sommige shared hosts (Apache + mod_security2)
        // PATCH op nieuwe URLs blokkeren met een 405 HTML response.
        Route::post('bar-duties/{barDuty}/self-assign', [BarDutyController::class, 'selfAssign']);

        // Staff groups
        Route::apiResource('staff-groups', StaffGroupController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::patch('staff-groups/{staffGroup}/members', [StaffGroupController::class, 'syncMembers']);
        // Volledig SwapMember-shape (incl. email + lidnummer + hasAppAccount) voor
        // de mobile TeamMembersPage. Aparte endpoint zodat de bestaande show()
        // response (kleine member-shape) niet breekt.
        Route::get('staff-groups/{staffGroup}/members-full', [StaffGroupController::class, 'fullMembers']);

        // Swap requests
        Route::get('swap-requests/incoming', [SwapRequestController::class, 'incoming']);
        Route::post('swap-requests', [SwapRequestController::class, 'store']);
        Route::patch('swap-requests/{swapRequest}/accept', [SwapRequestController::class, 'accept']);
        Route::patch('swap-requests/{swapRequest}/decline', [SwapRequestController::class, 'decline']);

        // Ruimtes reserveren. Achter de rol Ruimteplanning en de
        // moduleschakelaar van de club; die grendel zit in RoomController zelf,
        // want de routes hier dragen geen rol-middleware.
        //
        // De vaste paden staan vóór de route met {room}, anders vangt die
        // wildcard 'reserveringen' op als ruimte-id.
        Route::get('/rooms', [RoomController::class, 'index']);
        Route::get('/rooms/bezetting', [RoomController::class, 'bezetting']);
        Route::get('/rooms/reserveringen', [RoomController::class, 'mijne']);
        Route::post('/rooms/reserveringen/{reservation}/annuleren', [RoomController::class, 'annuleer']);
        Route::post('/rooms/{room}/reserveren', [RoomController::class, 'reserveer']);

        // Profiel
        // Kledingmaten. Ophalen geeft de regels van iedereen voor wie je mag
        // invullen: jezelf en je gekoppelde kinderen.
        Route::get('/profile/clothing', [ClothingController::class, 'index']);
        Route::post('/profile/clothing', [ClothingController::class, 'store']);
        // Het nummer op een kledingstuk, los van de maat.
        Route::post('/profile/clothing/number', [ClothingController::class, 'setNumber']);
        Route::get('/clothing/sizes', [ClothingController::class, 'sizes']);

        Route::patch('/profile/photo', [ProfileController::class, 'updatePhoto']);
        // Account zelf verwijderen (POST i.p.v. DELETE; shared-host-veilig)
        Route::post('/profile/delete', [ProfileController::class, 'destroy']);

        // Bug reports (throttle: 5 per minute)
        Route::post('/bug-reports', [BugReportController::class, 'store'])
             ->middleware(['throttle:5,1']);

        // Guardian / ouder-verzorger koppelingen
        Route::prefix('guardian')->group(function () {
            // Lid maakt een ouder/verzorger-account aan (directe goedkeuring)
            Route::post('/create-parent-account', [GuardianController::class, 'createParentAccount'])
                 ->middleware(['throttle:5,1']);

            // Ouder: verzoek indienen voor extra kind.
            //
            // De begrenzing blijft: met een lidnummer en een achternaam kun je
            // anders net zo lang raden tot je aan iemand hangt. Wel ruimer dan
            // vijf per minuut - wie zich vertypt bij twee kinderen zat er al
            // tegenaan, en dat is geen aanval maar een ouder met een telefoon.
            Route::post('/request', [GuardianController::class, 'request'])
                 ->middleware(['throttle:15,1', 'throttle:40,60']);

            // Kind: openstaande verzoeken ophalen (ook bij login)
            Route::get('/pending', [GuardianController::class, 'pendingForMe']);

            // Kind: reageren op een verzoek
            Route::post('/{guardianLink}/respond', [GuardianController::class, 'respond']);

            // Kind / ouder / beheerder: koppeling intrekken. Ook als POST,
            // want de hosting blokkeert DELETE - de app gebruikt die variant.
            Route::delete('/{guardianLink}/revoke', [GuardianController::class, 'revoke']);
            Route::post('/{guardianLink}/revoke', [GuardianController::class, 'revoke']);

            // Met wie ben ik gekoppeld, in beide richtingen (voor het profiel)
            Route::get('/links', [GuardianController::class, 'links']);

            // Ouder: eigen gekoppelde kinderen ophalen
            Route::get('/children', [GuardianController::class, 'children']);

            // Ouder: overzicht van eigen ingediende verzoeken
            Route::get('/my-requests', [GuardianController::class, 'myRequests']);

            // Ouder: basisgegevens van een gekoppeld kind bekijken
            Route::get('/members/{member}/data', [GuardianController::class, 'childData']);
        });

        // Sync (admin only)
        Route::middleware('role:super_admin|club_admin')->prefix('sync')->group(function () {
            Route::get('/status', [SyncController::class, 'status']);
            Route::post('/all', [SyncController::class, 'syncAll']);
            Route::post('/teams', [SyncController::class, 'syncTeams']);
            Route::post('/members', [SyncController::class, 'syncMembers']);
            Route::post('/matches', [SyncController::class, 'syncMatches']);
        });
    });
});
