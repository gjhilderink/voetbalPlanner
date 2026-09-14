<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AccessCodeResource\Pages;
use App\Models\AccessCode;
use App\Models\AgendaItem;
use App\Models\TicketType;
use App\Services\OrderService;
use App\Support\Qr;
use App\Support\TicketPdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * De uitgedeelde toegangscodes van een activiteit.
 *
 * Er is geen aparte "lijst": het agenda-item is het evenement. Codes hangen er
 * rechtstreeks aan, en een activiteit die alleen op "gratis voor leden" staat
 * heeft er helemaal geen nodig.
 */
class AccessCodeResource extends Resource
{
    protected static ?string $model = AccessCode::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-qr-code';
    protected static ?string $navigationLabel = 'Toegangscodes';
    protected static ?string $modelLabel = 'toegangscode';
    protected static ?string $pluralModelLabel = 'Toegangscodes';
    protected static string|\UnitEnum|null $navigationGroup = 'Beheer';
    protected static ?int $navigationSort = 9;
    protected static bool $isScopedToTenant = false;

    /** @var array<int, string> */
    private const ROLLEN = ['super_admin', 'club_admin', 'toegang'];

    public static function getEloquentQuery(): Builder
    {
        $tenant = filament()->getTenant();
        $user   = auth()->user();

        return parent::getEloquentQuery()
            // De bestelling erbij: de kolom "Uitgegeven" leest eruit of deze
            // code de deur al uit is, en zonder dit haalt elke rij hem apart op.
            ->with(['agendaItem', 'order'])
            ->when($tenant, fn (Builder $q) => $q->where('club_id', $tenant->id))
            ->when(
                ! $tenant && ! $user?->hasRole('super_admin') && $user?->club_id,
                fn (Builder $q) => $q->where('club_id', $user->club_id),
            );
    }

    /**
     * Staat de module aan bij deze club?
     *
     * Ook gebruikt door AccessEntryResource en AgendaItemResource, zodat de
     * regel op één plek staat. Een super-admin zonder gekozen club ziet alles;
     * die kijkt dan clubbreed en niet namens één club.
     */
    public static function moduleAan(): bool
    {
        $club = filament()->getTenant();
        if ($club) {
            return (bool) $club->access_enabled;
        }

        $user = auth()->user();
        if ($user?->hasRole('super_admin')) {
            return true;
        }

        return (bool) $user?->club?->access_enabled;
    }

    public static function canViewAny(): bool
    {
        return (auth()->user()?->hasAnyRole(self::ROLLEN) ?? false) && static::moduleAan();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    /**
     * De activiteiten waar codes bij kunnen: alles wat nog niet lang voorbij is.
     *
     * @return array<string, string>
     */
    public static function agendaOpties(): array
    {
        $tenant = filament()->getTenant();
        $user   = auth()->user();
        $clubId = $tenant?->id ?? $user?->club_id;

        return AgendaItem::query()
            ->when($clubId, fn (Builder $q) => $q->where('club_id', $clubId))
            ->where('starts_at', '>=', now()->subMonths(3))
            ->orderBy('starts_at')
            ->get(['id', 'title', 'starts_at'])
            ->mapWithKeys(fn (AgendaItem $item) => [
                $item->id => $item->title . ' — ' . ($item->starts_at?->format('d-m-Y H:i') ?? ''),
            ])
            ->all();
    }

    /**
     * Kan deze club vouchers uitgeven?
     *
     * Een uitgifte wordt een bestelling, en Bestellingen hoort bij de
     * ticketshop. Zonder die module zou de stapel wel worden vastgelegd maar
     * nergens te zien zijn - dan is afdrukken zonder uitgeven (het PDF-vel)
     * eerlijker dan een knop die iets wegstopt.
     */
    public static function vouchersMogelijk(): bool
    {
        return static::canCreate() && OrderResource::canViewAny();
    }

    /**
     * De kaartsoorten van één activiteit, om vouchers op af te boeken.
     *
     * @return array<string, string>
     */
    public static function soortOpties(?string $agendaItemId): array
    {
        if (! $agendaItemId) {
            return [];
        }

        return TicketType::query()
            ->where('agenda_item_id', $agendaItemId)
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(function (TicketType $soort): array {
                $over = $soort->beschikbaar();

                return [$soort->id => $soort->name
                    . ($over === null ? '' : ' (nog ' . $over . ' beschikbaar)')];
            })
            ->all();
    }

    /**
     * Codes uitgeven en als voucher terugsturen: één A4 per code.
     *
     * De grens op het aantal pagina's wordt hier gecontroleerd en niet pas bij
     * het maken van de pdf. Andersom zouden de codes als uitgegeven in de lijst
     * staan terwijl er nooit een voucher uit de printer kwam - en die codes zijn
     * daarna niet meer uit te geven.
     *
     * @param  EloquentCollection<int, AccessCode>  $codes
     */
    public static function vouchersUitgeven(
        EloquentCollection $codes,
        ?string $naam = null,
        ?string $soortId = null,
    ): ?StreamedResponse {
        // Al uitgegeven codes gaan er niet nog een keer uit. Wie een zoekgeraakt
        // vel opnieuw moet drukken doet dat bij de bestelling zelf; dan blijft
        // het één uitgifte in plaats van twee.
        $nieuw   = $codes->whereNull('order_id');
        $bekende = $codes->count() - $nieuw->count();

        if ($nieuw->isEmpty()) {
            Notification::make()
                ->title('Niets uit te geven')
                ->body('Deze codes staan al op een bestelling. Opnieuw afdrukken doe je bij Bestellingen, met "Kaarten als pdf".')
                ->warning()
                ->send();

            return null;
        }

        if ($nieuw->count() > TicketPdf::MAX_KAARTEN) {
            Notification::make()
                ->title('Te veel vouchers voor één bestand')
                ->body('Er passen ' . TicketPdf::MAX_KAARTEN . ' vouchers in één pdf; dit zijn er '
                    . $nieuw->count() . '. Geef ze in delen uit.')
                ->warning()
                ->persistent()
                ->send();

            return null;
        }

        $uitkomst = app(OrderService::class)->geefUit(
            $nieuw->pluck('id')->all(),
            $naam,
            auth()->user()?->email ?? '',
            $soortId,
        );

        if (! ($uitkomst['ok'] ?? false)) {
            Notification::make()
                ->title('Uitgeven ging niet')
                ->body(implode(' ', $uitkomst['fouten'] ?? []))
                ->danger()
                ->persistent()
                ->send();

            return null;
        }

        $order = $uitkomst['order'];

        Notification::make()
            ->title($uitkomst['aantal'] . ' vouchers uitgegeven')
            ->body('Ze staan nu als bestelling ' . $order->order_number . ' bij Bestellingen.'
                . ($bekende > 0 ? ' ' . $bekende . ' van de gekozen codes waren al uitgegeven en zitten er niet bij.' : ''))
            ->success()
            ->send();

        return OrderResource::kaartenDownload(
            AccessCode::where('order_id', $order->id)->orderBy('code')->get(),
            'vouchers-' . $order->order_number . '.pdf',
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                Forms\Components\Hidden::make('club_id')
                    ->default(fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id),

                Forms\Components\Select::make('agenda_item_id')
                    ->label('Activiteit')
                    ->options(fn () => static::agendaOpties())
                    ->searchable()
                    ->required()
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(64)
                    ->helperText('Dit staat straks in de QR. Hoofdletters en cijfers lezen het prettigst.')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('label')
                    ->label('Omschrijving')
                    ->maxLength(255)
                    ->helperText('Optioneel. Bijvoorbeeld een naam, zodat je bij de deur ziet voor wie de code was.')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('max_uses')
                    ->label('Maximaal aantal keer gebruiken')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(999)
                    ->default(1)
                    ->required()
                    ->columnSpan(1),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true)
                    ->helperText('Uit = de code wordt bij de ingang geweigerd, zonder hem te verwijderen.')
                    ->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('agendaItem.title')
                    ->label('Activiteit')
                    ->description(fn (AccessCode $record): string => $record->agendaItem?->starts_at?->format('d-m-Y H:i') ?? '')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Code gekopieerd')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('label')
                    ->label('Omschrijving')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(30),

                Tables\Columns\TextColumn::make('used_count')
                    ->label('Gebruikt')
                    ->badge()
                    ->alignCenter()
                    // Rood zodra hij op is: dat is precies wat je bij de deur
                    // wilt kunnen nakijken als iemand klaagt dat hij niet werkt.
                    ->color(fn (AccessCode $record): string => $record->used_count >= $record->max_uses ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state, AccessCode $record): string => $state . ' / ' . $record->max_uses),

                // Of de code de deur al uit is. Staat er een bestelnummer, dan
                // is hij verkocht of als voucher afgedrukt en hoort hij niet
                // nog een keer uitgedeeld te worden.
                Tables\Columns\TextColumn::make('order.order_number')
                    ->label('Uitgegeven')
                    ->badge()
                    ->color('success')
                    ->placeholder('nog niet')
                    ->description(fn (AccessCode $record): ?string => $record->order?->isUitgegeven()
                        ? $record->order->created_at?->format('d-m-Y')
                        : $record->order?->buyer_name),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actief')
                    ->boolean()
                    ->alignCenter()
                    ->width(70),
            ])
            ->defaultSort('code')
            ->filters([
                Tables\Filters\SelectFilter::make('agenda_item_id')
                    ->label('Activiteit')
                    ->options(fn () => static::agendaOpties()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actief'),

                Tables\Filters\TernaryFilter::make('uitgegeven')
                    ->label('Uitgegeven')
                    ->placeholder('Alles')
                    ->trueLabel('Al uitgegeven')
                    ->falseLabel('Nog niet uitgegeven')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('order_id'),
                        false: fn (Builder $q) => $q->whereNull('order_id'),
                        blank: fn (Builder $q) => $q,
                    ),

                Tables\Filters\Filter::make('op')
                    ->label('Alleen opgebruikte codes')
                    ->query(fn (Builder $q) => $q->whereColumn('used_count', '>=', 'max_uses')),
            ])
            ->actions([
                Actions\Action::make('qr')
                    ->label('QR bekijken')
                    ->icon('heroicon-o-qr-code')
                    ->color('gray')
                    ->modalHeading(fn (AccessCode $record): string => 'Code ' . $record->code)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Sluiten')
                    ->modalWidth('sm')
                    // Een view en geen formulier: er valt niets in te vullen.
                    // De download loopt via een data-URI, zodat er geen extra
                    // route nodig is die ook weer afgeschermd moet worden.
                    ->modalContent(fn (AccessCode $record) => view('filament.access.qr-modal', [
                        'code'    => $record,
                        'dataUri' => Qr::pngDataUri($record->code, 320),
                        'download' => Qr::pngDataUri($record->code, 640),
                    ])),

                Actions\Action::make('reset')
                    ->label('Teller terugzetten')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Teller terugzetten')
                    ->modalDescription('De code is daarna weer te gebruiken. De binnenkomsten die al geteld zijn blijven staan in het overzicht.')
                    ->visible(fn (AccessCode $record): bool => $record->used_count > 0)
                    ->action(function (AccessCode $record): void {
                        $record->update(['used_count' => 0]);

                        Notification::make()
                            ->title('Teller terugzet')
                            ->body('Code ' . $record->code . ' is weer te gebruiken.')
                            ->success()
                            ->send();
                    }),

                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Buiten het menu: dit is wat je met een selectie wilt doen.
                Actions\BulkAction::make('vouchers')
                    ->label('Vouchers uitgeven')
                    ->icon('heroicon-o-ticket')
                    ->color('gray')
                    ->visible(fn (): bool => static::vouchersMogelijk())
                    ->requiresConfirmation()
                    ->modalHeading('Vouchers uitgeven')
                    ->modalDescription('De gekozen codes worden vastgelegd als bestelling en komen als pdf uit de printer, één A4 per code. Daarna staan ze bij Bestellingen en zijn ze in deze lijst als uitgegeven te herkennen. Codes die al uitgegeven zijn blijven buiten deze stapel.')
                    ->modalSubmitActionLabel('Uitgeven en pdf maken')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (Collection $records): ?StreamedResponse => static::vouchersUitgeven(
                        AccessCode::whereIn('id', $records->pluck('id'))->orderBy('code')->get(),
                    )),

                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccessCodes::route('/'),
            'create' => Pages\CreateAccessCode::route('/create'),
            'edit'   => Pages\EditAccessCode::route('/{record}/edit'),
        ];
    }
}
