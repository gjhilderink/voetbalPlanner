<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\AccessCode;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Geld;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * De bestellingen uit de ticketshop.
 *
 * Alleen lezen: een bestelling is een vastgelegd feit. Wat je er wél mee kunt
 * is de mail opnieuw sturen en de bestelling intrekken - dat laatste zet de
 * codes op niet-actief, zodat ze bij de ingang geweigerd worden. Geld
 * terugstorten gebeurt bij Pay.nl en niet hier; dit scherm weet niets van de
 * rekening.
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

    public static function canDelete(Model $record): bool
    {
        return false;
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

                Tables\Filters\SelectFilter::make('agenda_item_id')
                    ->label('Activiteit')
                    ->options(fn () => AccessCodeResource::agendaOpties()),
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

                Actions\Action::make('mail')
                    ->label('Mail opnieuw sturen')
                    ->icon('heroicon-o-envelope')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalHeading('Kaarten opnieuw mailen')
                    ->modalDescription(fn (Order $record): string => 'De kaarten gaan opnieuw naar ' . $record->buyer_email . '.')
                    ->visible(fn (Order $record): bool => $record->isBetaald())
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
                    ->modalHeading('Bestelling intrekken')
                    ->modalDescription('De kaarten van deze bestelling worden bij de ingang geweigerd. Het geld terugstorten doe je bij Pay.nl; dat gebeurt hier niet.')
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
