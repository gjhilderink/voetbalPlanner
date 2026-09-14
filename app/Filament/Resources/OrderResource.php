<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\AccessCode;
use App\Models\AccessEntry;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Geld;
use App\Support\TicketPdf;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * De bestellingen uit de ticketshop.
 *
 * Niets aan te passen: een bestelling is een vastgelegd feit. Wat je er wél mee
 * kunt is de mail opnieuw sturen en de bestelling intrekken - dat laatste zet de
 * codes op niet-actief, zodat ze bij de ingang geweigerd worden. Geld
 * terugstorten gebeurt bij Pay.nl en niet hier; dit scherm weet niets van de
 * rekening.
 *
 * Opruimen kan wel, maar alleen wat geweest is: zie canDelete() hieronder en
 * Order::magWeg(). Intrekken en verwijderen zijn niet hetzelfde - het eerste
 * sluit de deur voor die kaarten, het tweede wist dat ze bestonden.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'Bestellingen';
    protected static ?string $modelLabel = 'bestelling';
    protected static ?string $pluralModelLabel = 'Bestellingen';
    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';
    protected static ?int $navigationSort = 11;
    protected static bool $isScopedToTenant = false;

    /** @var array<int, string> */
    private const ROLLEN = ['super_admin', 'club_admin', 'toegang'];

    public static function canViewAny(): bool
    {
        if (! auth()->user()?->hasAnyRole(self::ROLLEN)) {
            return false;
        }

        $club = filament()->getTenant();
        if ($club) {
            return (bool) $club->ticketshop_enabled;
        }

        $user = auth()->user();

        return $user?->hasRole('super_admin')
            ? true
            : (bool) $user?->club?->ticketshop_enabled;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    /**
     * Verwijderen mag, maar niet alles en niet door iedereen.
     *
     * De rol 'toegang' is die van de vrijwilliger bij de ingang: die hoort
     * bestellingen op te kunnen zoeken, niet op te ruimen. Wát er weg mag staat
     * bij Order::magWeg(), zodat de knop in de tabel en de groepsactie niet uit
     * elkaar kunnen lopen.
     */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof Order
            && $record->magWeg()
            && (bool) auth()->user()?->hasAnyRole(['super_admin', 'club_admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        } elseif (! $user?->hasRole('super_admin') && $user?->club_id) {
            $query->where('club_id', $user->club_id);
        }

        return $query->with(['agendaItem', 'lines']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Bestelling')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('agendaItem.title')
                    ->label('Activiteit')
                    ->description(fn (Order $record): string => $record->agendaItem?->starts_at?->format('d-m-Y H:i') ?? '')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('buyer_name')
                    ->label('Koper')
                    ->description(fn (Order $record): string => $record->buyer_email)
                    ->searchable(['buyer_name', 'buyer_email']),

                Tables\Columns\TextColumn::make('kaarten')
                    ->label('Kaarten')
                    ->alignCenter()
                    ->getStateUsing(fn (Order $record): int => $record->aantalKaarten()),

                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Bedrag')
                    ->alignEnd()
                    ->formatStateUsing(fn (int $state): string => Geld::euro($state))
                    ->sortable(),

                // Een uitgifte staat op betaald zonder dat er geld langs is
                // gekomen. Zonder deze kolom is dat een bestelling van € 0 waar
                // niemand iets van weet.
                Tables\Columns\TextColumn::make('source')
                    ->label('Herkomst')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Order::SOURCES[$state] ?? $state)
                    ->color(fn ($state) => $state === Order::SOURCE_ISSUED ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Order::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        Order::STATUS_PAID    => 'success',
                        Order::STATUS_PENDING => 'gray',
                        default               => 'danger',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Besteld op')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('mail_sent_at')
                    ->label('Mail verstuurd')
                    ->dateTime('d-m-Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(Order::STATUSES),

                Tables\Filters\SelectFilter::make('source')
                    ->label('Herkomst')
                    ->options(Order::SOURCES),

                Tables\Filters\SelectFilter::make('agenda_item_id')
                    ->label('Activiteit')
                    ->options(fn () => AccessCodeResource::agendaOpties()),

                // Om op te ruimen: zo staat in één keer bij elkaar wat weg mag,
                // zonder per activiteit na te lopen wanneer die ook alweer was.
                Tables\Filters\TernaryFilter::make('periode')
                    ->label('Periode')
                    ->placeholder('Alles')
                    ->trueLabel('Activiteit is geweest')
                    ->falseLabel('Activiteit moet nog komen')
                    ->queries(
                        true:  fn (Builder $q) => $q->whereHas('agendaItem', fn (Builder $a) => $a->where('starts_at', '<', now())),
                        false: fn (Builder $q) => $q->whereHas('agendaItem', fn (Builder $a) => $a->where('starts_at', '>=', now())),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->actions([
                Actions\Action::make('codes')
                    ->label('Kaarten bekijken')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->visible(fn (Order $record): bool => $record->isBetaald())
                    ->modalHeading(fn (Order $record): string => 'Bestelling ' . $record->order_number)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->modalWidth('lg')
                    ->modalContent(fn (Order $record) => view('filament.access.order-codes', [
                        'order' => $record->load(['lines', 'accessCodes']),
                    ])),

                Actions\Action::make('pdf')
                    ->label('Kaarten als pdf')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->visible(fn (Order $record): bool => $record->isBetaald())
                    ->action(fn (Order $record): ?StreamedResponse => self::kaartenDownload(
                        AccessCode::where('order_id', $record->id)->orderBy('code')->get(),
                        'kaarten-' . $record->order_number . '.pdf',
                    )),

                Actions\Action::make('mail')
                    ->label('Mail opnieuw sturen')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Kaarten opnieuw mailen')
                    ->modalDescription(fn (Order $record): string => 'De kaarten gaan opnieuw naar ' . $record->buyer_email . '.')
                    // Niet bij een uitgifte: daar staat het adres van de
                    // beheerder als koper, en die hoeft zijn eigen vouchers niet
                    // gemaild te krijgen. Afdrukken is daar de bedoelde weg.
                    ->visible(fn (Order $record): bool => $record->isBetaald() && ! $record->isUitgegeven())
                    ->action(function (Order $record): void {
                        $gelukt = app(OrderService::class)->stuurTickets($record);

                        Notification::make()
                            ->title($gelukt ? 'Mail verstuurd' : 'Versturen mislukt')
                            ->body($gelukt
                                ? 'De kaarten zijn opnieuw naar ' . $record->buyer_email . ' gestuurd.'
                                : 'Kijk in de logboeken onder [Ticketshop] wat er misging.')
                            ->color($gelukt ? 'success' : 'danger')
                            ->persistent(! $gelukt)
                            ->send();
                    }),

                Actions\Action::make('intrekken')
                    ->label('Intrekken')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record): string => $record->isUitgegeven()
                        ? 'Uitgifte intrekken'
                        : 'Bestelling intrekken')
                    ->modalDescription(fn (Order $record): string => $record->isUitgegeven()
                        ? 'De afgedrukte vouchers van deze stapel worden bij de ingang geweigerd. Doe dit als een vel zoekraakt of verkeerd is uitgedeeld; de codes blijven als uitgegeven in de lijst staan, zodat ze niet opnieuw de deur uit gaan.'
                        : 'De kaarten van deze bestelling worden bij de ingang geweigerd. Het geld terugstorten doe je bij Pay.nl; dat gebeurt hier niet.')
                    ->visible(fn (Order $record): bool => $record->isBetaald())
                    ->action(function (Order $record): void {
                        AccessCode::where('order_id', $record->id)->update(['is_active' => false]);
                        $record->update(['status' => Order::STATUS_CANCELLED]);

                        Notification::make()
                            ->title('Bestelling ingetrokken')
                            ->body('De kaarten worden bij de ingang geweigerd.')
                            ->success()
                            ->send();
                    }),

                Actions\Action::make('verwijderen')
                    ->label('Verwijderen')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (Order $record): string => $record->isUitgegeven()
                        ? 'Uitgifte verwijderen'
                        : 'Bestelling verwijderen')
                    ->modalDescription(fn (Order $record): string => self::watVerdwijnt($record))
                    ->modalSubmitActionLabel('Definitief verwijderen')
                    ->visible(fn (Order $record): bool => self::canDelete($record))
                    ->action(function (Order $record): void {
                        if (! self::canDelete($record)) {
                            Notification::make()
                                ->title('Deze bestelling blijft staan')
                                ->body('De activiteit moet nog komen, of je mag hier niet opruimen.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $nummer = $record->order_number;
                        $weg    = app(OrderService::class)->verwijder($record);

                        Notification::make()
                            ->title('Bestelling ' . $nummer . ' verwijderd')
                            ->body($weg['kaarten'] > 0
                                ? self::telwoord($weg['kaarten'], 'kaart', 'kaarten') . ' meeverdwenen.'
                                : 'Er hingen geen kaarten aan deze bestelling.')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                // Afdrukken staat buiten het menu: daar gaat deze lijst meestal
                // voor open, en dan hoort het niet achter drie puntjes te
                // zitten. Opruimen juist wel - een stap extra voor iets dat niet
                // terugkomt.
                Actions\BulkAction::make('kaarten_pdf')
                    ->label('Kaarten als pdf')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records): ?StreamedResponse {
                        $betaald = $records->filter(fn (Order $order): bool => $order->isBetaald());

                        if ($betaald->isEmpty()) {
                            Notification::make()
                                ->title('Niets af te drukken')
                                ->body('Alleen betaalde bestellingen hebben kaarten.')
                                ->warning()
                                ->send();

                            return null;
                        }

                        return self::kaartenDownload(
                            AccessCode::whereIn('order_id', $betaald->pluck('id'))
                                ->orderBy('order_id')
                                ->orderBy('code')
                                ->get(),
                            'kaarten-' . now()->format('Y-m-d') . '.pdf',
                        );
                    }),

                Actions\BulkActionGroup::make([
                    Actions\BulkAction::make('verwijderen')
                        ->label('Verwijderen')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Bestellingen verwijderen')
                        ->modalDescription('De gekozen bestellingen verdwijnen uit de lijst, met hun bestelregels, hun kaarten en de binnenkomsten die op die kaarten geregistreerd staan. Alleen wat weg mag gaat weg: bestellingen bij een activiteit die nog moet komen blijven staan, tenzij ze verlopen of mislukt zijn. Dit is niet ongedaan te maken.')
                        ->modalSubmitActionLabel('Definitief verwijderen')
                        ->deselectRecordsAfterCompletion()
                        ->visible(fn (): bool => (bool) auth()->user()?->hasAnyRole(['super_admin', 'club_admin']))
                        ->action(function (Collection $records): void {
                            $dienst  = app(OrderService::class);
                            $weg     = 0;
                            $kaarten = 0;
                            $blijft  = 0;

                            foreach ($records as $record) {
                                // Per bestelling opnieuw langs dezelfde regel:
                                // welke rijen er aangevinkt zijn komt uit de
                                // browser, en daar hangt niet van af wat weg mag.
                                if (! self::canDelete($record)) {
                                    $blijft++;

                                    continue;
                                }

                                $uitkomst = $dienst->verwijder($record);
                                $kaarten += $uitkomst['kaarten'];
                                $weg++;
                            }

                            $tekst = $kaarten > 0
                                ? self::telwoord($kaarten, 'kaart', 'kaarten') . ' meeverdwenen.'
                                : 'Er hingen geen kaarten aan.';

                            if ($blijft > 0) {
                                $tekst .= ' ' . self::telwoord($blijft, 'bestelling', 'bestellingen')
                                    . ' bleef staan: de activiteit moet nog komen.';
                            }

                            Notification::make()
                                ->title(self::telwoord($weg, 'bestelling', 'bestellingen') . ' verwijderd')
                                ->body($tekst)
                                ->color($weg > 0 ? 'success' : 'warning')
                                ->send();
                        }),
                ]),
            ]);
    }

    /**
     * Wat er precies verdwijnt, voor het venster dat om bevestiging vraagt.
     *
     * Met de aantallen erbij en niet alleen "en alles wat erbij hoort": een
     * bestelling waar twintig gescande kaarten aan hangen hoort er anders uit te
     * zien dan een mislukte betaling van vorige week.
     */
    private static function watVerdwijnt(Order $order): string
    {
        $codeIds = $order->accessCodes()->pluck('id')->all();
        $kaarten = count($codeIds);
        $scans   = $codeIds === [] ? 0 : AccessEntry::whereIn('access_code_id', $codeIds)->count();

        $tekst = 'Bestelling ' . $order->order_number . ' van ' . $order->buyer_name
            . ' verdwijnt uit de lijst, met de bestelregels';

        if ($kaarten > 0) {
            $tekst .= ', ' . self::telwoord($kaarten, 'kaart', 'kaarten');
        }

        if ($scans > 0) {
            $tekst .= ' en ' . self::telwoord($scans, 'binnenkomst', 'binnenkomsten') . ' bij de ingang';
        }

        return $tekst . '. Dit is niet ongedaan te maken. Wat er betaald is blijft bij Pay.nl staan, '
            . 'en moeten de kaarten alleen geweigerd worden bij de ingang, gebruik dan Intrekken.';
    }

    /** "1 kaart" of "3 kaarten". */
    private static function telwoord(int $aantal, string $enkel, string $meer): string
    {
        return $aantal . ' ' . ($aantal === 1 ? $enkel : $meer);
    }

    /**
     * De kaarten als pdf terugsturen: één A4 per kaart.
     *
     * Hier en niet in TicketPdf, omdat de grens tussen "veel" en "te veel" een
     * melding voor de beheerder oplevert en geen uitzondering. Zonder deze
     * controle levert een activiteit met honderden kaarten een php-worker op die
     * zonder geheugen valt, en dan komt er helemaal niets terug.
     *
     * @param  EloquentCollection<int, AccessCode>  $codes
     */
    public static function kaartenDownload(EloquentCollection $codes, string $bestandsnaam): ?StreamedResponse
    {
        if ($codes->isEmpty()) {
            Notification::make()
                ->title('Niets af te drukken')
                ->body('Er zijn geen kaarten gevonden.')
                ->warning()
                ->send();

            return null;
        }

        if ($codes->count() > TicketPdf::MAX_KAARTEN) {
            Notification::make()
                ->title('Te veel kaarten voor één bestand')
                ->body('Er passen ' . TicketPdf::MAX_KAARTEN . ' kaarten in één pdf; dit zijn er '
                    . $codes->count() . '. Druk ze in delen af, bijvoorbeeld door minder '
                    . 'bestellingen tegelijk aan te vinken.')
                ->warning()
                ->persistent()
                ->send();

            return null;
        }

        return TicketPdf::download(TicketPdf::kaarten($codes), $bestandsnaam);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
