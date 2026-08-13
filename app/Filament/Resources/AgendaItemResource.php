<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\AgendaItemResource\Pages;
use App\Filament\Resources\AgendaItemResource\RelationManagers\RegistrationsRelationManager;
use App\Filament\Support\TeamFilter;
use App\Models\AgendaCategory;
use App\Models\AgendaItem;
use App\Models\StaffGroup;
use App\Services\FcmService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AgendaItemResource extends Resource
{
    protected static ?string $model = AgendaItem::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-calendar-days';
    protected static ?string $navigationLabel                  = 'Verenigingsagenda';
    protected static ?string $modelLabel                       = 'Activiteit';
    protected static ?string $pluralModelLabel                 = 'Activiteiten';
    protected static string|\UnitEnum|null $navigationGroup    = 'Planning';
    protected static ?int    $navigationSort                   = 10;
    protected static bool    $isScopedToTenant                 = false;

    /** Meekijken mag breed; beheren blijft bij club-beheer. */
    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach', 'bar_commissie']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        } elseif (! auth()->user()?->hasRole('super_admin')) {
            $query->where('club_id', auth()->user()?->club_id);
        }

        // Twee aggregaten: aangemelde personen én hun introducés. De capaciteit
        // telt introducés mee, dus de kolom moet dat ook doen.
        return $query
            ->with(['category', 'teams:id,name', 'staffGroups:id,name'])
            ->withCount(['registrations as going_people' => fn ($q) => $q->going()])
            ->withSum(['registrations as going_guests' => fn ($q) => $q->going()], 'guest_count');
    }

    /** Categorieën van de eigen club, voor de keuzelijst. */
    public static function categoryOptions(): array
    {
        $clubId = filament()->getTenant()?->id ?? auth()->user()?->club_id;

        return AgendaCategory::query()
            ->where('club_id', $clubId)
            ->activeOrdered()
            ->pluck('name', 'id')
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $clubId = fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id;

        return $schema->components([
            Section::make('Activiteit')->columns(2)->schema([
                Forms\Components\Hidden::make('club_id')->default($clubId),

                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(200)
                    ->columnSpanFull(),

                Forms\Components\Select::make('agenda_category_id')
                    ->label('Categorie')
                    ->options(fn () => self::categoryOptions())
                    ->searchable()
                    ->placeholder('Geen categorie'),

                Forms\Components\TextInput::make('location')
                    ->label('Locatie')
                    ->maxLength(200),

                Forms\Components\TextInput::make('summary')
                    ->label('Korte omschrijving')
                    ->maxLength(300)
                    ->helperText('Eén regel voor de agendakaart en het dashboard in de app.')
                    ->columnSpanFull(),

                Forms\Components\Textarea::make('description')
                    ->label('Volledige omschrijving')
                    ->rows(8)
                    ->columnSpanFull(),

                Forms\Components\FileUpload::make('image_path')
                    ->label('Afbeelding')
                    ->image()
                    ->maxSize(5120)
                    ->disk('public')
                    ->directory('agenda_images')
                    ->imageEditor()
                    ->columnSpanFull(),
            ]),

            Section::make('Wanneer')->columns(2)->schema([
                Forms\Components\Toggle::make('is_all_day')
                    ->label('Hele dag')
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('starts_at')
                    ->label('Begint op')
                    ->required()
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i'),

                Forms\Components\DateTimePicker::make('ends_at')
                    ->label('Eindigt op')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->after('starts_at')
                    ->helperText('Laat leeg als de activiteit geen vaste eindtijd heeft. Vul een latere dag in voor een meerdaagse activiteit.'),

                Forms\Components\TextInput::make('location_url')
                    ->label('Routelink')
                    ->url()
                    ->maxLength(500),

                Forms\Components\TextInput::make('external_url')
                    ->label('Externe link')
                    ->url()
                    ->maxLength(500)
                    ->helperText('Bijvoorbeeld een inschrijfformulier of een pagina op de clubsite.'),

                Forms\Components\Textarea::make('extra_info')
                    ->label('Aanvullende informatie')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Section::make('Voor wie')->columns(1)->schema([
                Forms\Components\Radio::make('audience')
                    ->label('Doelgroep')
                    ->options(AgendaItem::AUDIENCES)
                    ->default(AgendaItem::AUDIENCE_EVERYONE)
                    ->required()
                    ->live(),

                Forms\Components\Select::make('teams')
                    ->label('Elftallen')
                    ->multiple()
                    ->relationship('teams', 'name', modifyQueryUsing: fn (Builder $query) => TeamFilter::scopeQuery($query))
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('audience') === AgendaItem::AUDIENCE_SELECTION),

                Forms\Components\Select::make('staffGroups')
                    ->label('Groepen (commissies, vrijwilligers)')
                    ->multiple()
                    ->relationship('staffGroups', 'name', modifyQueryUsing: fn (Builder $query) => $query
                        ->where('club_id', filament()->getTenant()?->id ?? auth()->user()?->club_id)
                        ->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('audience') === AgendaItem::AUDIENCE_SELECTION)
                    ->helperText('Groepen beheer je onder Leden → Staf & commissies.'),
            ]),

            Section::make('Aanmelden')->columns(2)->schema([
                Forms\Components\Toggle::make('registration_enabled')
                    ->label('Leden kunnen zich aanmelden')
                    ->live()
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('registration_closes_at')
                    ->label('Aanmelden kan tot')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),

                Forms\Components\TextInput::make('capacity')
                    ->label('Maximaal aantal personen')
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Leeg = onbeperkt. Introducés tellen mee.')
                    ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),

                Forms\Components\Toggle::make('allow_guests')
                    ->label('Introducés toegestaan')
                    ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),

                Forms\Components\Toggle::make('show_participants')
                    ->label('Deelnemerslijst zichtbaar in de app')
                    ->default(true)
                    ->visible(fn (Get $get): bool => (bool) $get('registration_enabled')),
            ]),

            Section::make('Publicatie')->columns(2)->schema([
                Forms\Components\Toggle::make('is_published')
                    ->label('Gepubliceerd')
                    ->default(true)
                    ->helperText('Niet-gepubliceerde activiteiten zijn alleen hier zichtbaar.'),

                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Publiceren vanaf')
                    ->seconds(false)
                    ->displayFormat('d-m-Y H:i')
                    ->helperText('Leeg = direct zichtbaar.'),

                Forms\Components\Toggle::make('is_highlighted')
                    ->label('Uitgelicht')
                    ->helperText('Zet deze activiteit bovenaan in "Binnenkort" op het dashboard.'),

                Forms\Components\Toggle::make('send_push')
                    ->label('Push-melding sturen')
                    ->helperText('Stuurt bij het opslaan een melding naar de app. Alleen als de activiteit gepubliceerd is.')
                    ->dehydrated(false)
                    ->visibleOn('create'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('')
                    ->disk('public')
                    ->height(40)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Wanneer')
                    ->sortable()
                    ->formatStateUsing(fn ($state, AgendaItem $record): string => $record->is_all_day
                        ? $state->format('d-m-Y')
                        : $state->format('d-m-Y H:i')),

                Tables\Columns\TextColumn::make('title')
                    ->label('Activiteit')
                    ->searchable()
                    ->limit(50)
                    ->description(fn (AgendaItem $record): ?string => $record->summary),

                Tables\Columns\ColorColumn::make('category.color')->label(''),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categorie')
                    ->badge()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('audience')
                    ->label('Voor wie')
                    ->badge()
                    ->formatStateUsing(fn ($state, AgendaItem $record): string => $state === AgendaItem::AUDIENCE_EVERYONE
                        ? 'Hele vereniging'
                        : Str::limit($record->audienceLabel(), 30))
                    ->color(fn ($state): string => $state === AgendaItem::AUDIENCE_EVERYONE ? 'gray' : 'info'),

                Tables\Columns\TextColumn::make('going_people')
                    ->label('Aangemeld')
                    ->badge()
                    ->color(fn (AgendaItem $record): string => $record->capacity
                        && (int) $record->going_people + (int) $record->going_guests >= $record->capacity
                            ? 'danger'
                            : 'gray')
                    ->formatStateUsing(function ($state, AgendaItem $record): string {
                        $total = (int) $state + (int) $record->going_guests;

                        return $record->capacity ? "{$total} / {$record->capacity}" : (string) $total;
                    }),

                Tables\Columns\IconColumn::make('is_published')->label('Live')->boolean(),
            ])
            ->filters([
                // Standaard (blanco keuze) toont alleen wat nog komt.
                Tables\Filters\TernaryFilter::make('periode')
                    ->label('Periode')
                    ->placeholder('Alleen komende')
                    ->trueLabel('Alles (incl. verleden)')
                    ->falseLabel('Alleen verleden')
                    ->queries(
                        true:  fn (Builder $query) => $query,
                        false: fn (Builder $query) => $query->where('starts_at', '<', now()),
                        blank: fn (Builder $query) => $query->upcoming(),
                    ),
                Tables\Filters\SelectFilter::make('agenda_category_id')
                    ->label('Categorie')
                    ->options(fn () => self::categoryOptions())
                    ->placeholder('Alle categorieën'),
                Tables\Filters\SelectFilter::make('audience')
                    ->label('Doelgroep')
                    ->options(AgendaItem::AUDIENCES),
                Tables\Filters\TernaryFilter::make('is_published')->label('Gepubliceerd'),
            ])
            ->defaultSort('starts_at')
            ->actions([
                Actions\Action::make('togglePublish')
                    ->label(fn (AgendaItem $record): string => $record->is_published ? 'Depubliceren' : 'Publiceren')
                    ->icon(fn (AgendaItem $record): string => $record->is_published ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (AgendaItem $record): string => $record->is_published ? 'gray' : 'success')
                    ->visible(fn (AgendaItem $record): bool => self::canEdit($record))
                    ->requiresConfirmation()
                    ->action(function (AgendaItem $record): void {
                        $record->update(['is_published' => ! $record->is_published]);

                        Notification::make()
                            ->success()
                            ->title($record->is_published ? 'Activiteit gepubliceerd' : 'Activiteit gedepubliceerd')
                            ->send();
                    }),

                Actions\Action::make('sendPush')
                    ->label('Melding sturen')
                    ->icon('heroicon-o-bell-alert')
                    ->color('warning')
                    ->visible(fn (AgendaItem $record): bool => self::canEdit($record) && $record->is_published)
                    ->form(fn (AgendaItem $record): array => [
                        Forms\Components\TextInput::make('title')
                            ->label('Titel')
                            ->default($record->title)
                            ->required(),
                        Forms\Components\Textarea::make('body')
                            ->label('Bericht')
                            ->default(Str::limit(trim(strip_tags((string) ($record->summary ?: $record->description))), 140))
                            ->rows(3)
                            ->required(),
                    ])
                    ->action(function (AgendaItem $record, array $data): void {
                        self::pushToAudience($record, $data['title'], $data['body']);
                    }),

                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Stuurt een push voor deze activiteit. Clubbrede items gaan naar het
     * bestaande 'all_users'-topic; gerichte items naar de persoonlijke topics
     * van de betrokken accounts, omdat team-topics nog niet bestaan.
     * Faalt stil — FcmService logt zelf en opslaan mag er nooit op stuklopen.
     */
    public static function pushToAudience(AgendaItem $item, string $title, string $body): void
    {
        $fcm  = app(FcmService::class);
        $data = ['initialPageName' => 'AgendaDetailPage', 'parameterData' => json_encode(['agendaItemId' => $item->id])];

        if ($item->audience === AgendaItem::AUDIENCE_EVERYONE) {
            $ok = $fcm->sendToTopic('all_users', $title, $body, $data);

            Notification::make()
                ->title($ok ? 'Melding verstuurd naar alle gebruikers.' : 'Melding niet verstuurd (controleer de FCM-configuratie).')
                ->{$ok ? 'success' : 'warning'}()
                ->send();

            return;
        }

        $emails = self::audienceEmails($item);
        $sent   = 0;

        foreach ($emails as $email) {
            if ($fcm->sendToTopic('user_' . FcmService::sanitizeTopicEmail($email), $title, $body, $data)) {
                $sent++;
            }
        }

        Notification::make()
            ->title($sent > 0
                ? "Melding verstuurd naar {$sent} van de {$emails->count()} ontvangers."
                : 'Melding niet verstuurd (geen ontvangers of FCM niet geconfigureerd).')
            ->{$sent > 0 ? 'success' : 'warning'}()
            ->send();
    }

    /**
     * E-mailadressen van de accounts in de doelgroep: accounts gekoppeld aan de
     * gekozen elftallen (direct én via hun lid) plus de leden en losse accounts
     * in de gekozen groepen.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function audienceEmails(AgendaItem $item): \Illuminate\Support\Collection
    {
        $teamIds  = $item->teams->pluck('id');
        $groupIds = $item->staffGroups->pluck('id');

        $fromTeams = \App\Models\User::query()
            ->whereNotNull('email')
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereHas('managedTeams', fn ($t) => $t->whereIn('teams.id', $teamIds))
                ->orWhereHas('member.teams', fn ($t) => $t->whereIn('teams.id', $teamIds)))
            ->pluck('email');

        $fromGroups = \App\Models\User::query()
            ->whereNotNull('email')
            ->where('is_active', true)
            ->where(fn ($q) => $q
                ->whereHas('staffGroups', fn ($g) => $g->whereIn('staff_groups.id', $groupIds))
                ->orWhereHas('member.staffGroups', fn ($g) => $g->whereIn('staff_groups.id', $groupIds)))
            ->pluck('email');

        return $fromTeams->merge($fromGroups)->unique()->values();
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAgendaItems::route('/'),
            'create' => Pages\CreateAgendaItem::route('/create'),
            'edit'   => Pages\EditAgendaItem::route('/{record}/edit'),
        ];
    }
}
