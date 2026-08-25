<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Filament\Support\TeamFilter;
use App\Models\Member;
use App\Services\WhatsAppService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class MemberResource extends Resource
{
    protected static ?string $model = Member::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Leden';
    protected static ?string $modelLabel = 'Lid';
    protected static ?string $pluralModelLabel = 'Leden';
    protected static ?int $navigationSort = 2;
    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Persoonsgegevens')->schema([
                // Alleen tonen, niet bewerken: de pasfoto komt uit Sportlink en
                // wordt bij elke ledensync opnieuw opgehaald. Wie hem hier zou
                // kunnen wijzigen, ziet die wijziging de volgende sync verdwijnen.
                Forms\Components\Placeholder::make('member_photo')
                    ->label('Pasfoto')
                    ->content(fn (?Member $record): HtmlString => new HtmlString(
                        '<img src="' . e($record?->photoUrl() ?? '') . '" alt="" '
                        . 'style="width:96px;height:96px;border-radius:9999px;object-fit:cover;'
                        . 'border:1px solid rgb(var(--gray-200));">'
                    ))
                    ->helperText(fn (?Member $record): string => $record?->profile_photo
                        ? 'Door het lid zelf in de app geüpload.'
                        : 'Uit Sportlink, bijgewerkt bij de ledensync.')
                    // Zonder foto liever niets dan een leeg kader.
                    ->visible(fn (?Member $record): bool => (bool) $record?->photoUrl())
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('name')
                    ->label('Naam')
                    ->required()
                    ->maxLength(255),
                // Hoort bij de speler en niet bij een opstelling: hetzelfde
                // nummer het hele seizoen. De app zet het op de pion.
                Forms\Components\TextInput::make('shirt_number')
                    ->label('Rugnummer')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(99)
                    ->helperText('Wordt in de app op het veld getoond.'),
                Forms\Components\DatePicker::make('date_of_birth')
                    ->label('Geboortedatum'),
                Forms\Components\Select::make('role')
                    ->label('Rol')
                    ->options([
                        'player'  => 'Speler',
                        'coach'   => 'Coach',
                        'medical' => 'Medische staf',
                        'staff'   => 'Overige staf',
                    ])
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->label('Actief')
                    ->default(true),
                Forms\Components\TextInput::make('external_id')
                    ->label('Extern ID (relatiecode)')
                    ->disabled(),
            ])->columns(2),

            Section::make('Communicatie')->schema([
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Mobiel nummer')
                    ->tel()
                    ->maxLength(20),
            ])->columns(2),

            Section::make('Teams & functies')->schema([
                // Per gekoppeld team een functie (speler/coach/leider/assistent).
                // Handmatig via dit panel gekoppelde teams worden is_manual = true
                // zodat de Sportlink-sync ze niet loskoppelt. Laden/opslaan gebeurt
                // via de Create/Edit-pagina hooks (syncTeamFunctions).
                Forms\Components\Repeater::make('team_functions')
                    ->label('Teams & functies')
                    ->helperText('Elk lid moet aan minimaal één team gekoppeld zijn.')
                    ->schema([
                        Forms\Components\Select::make('team_id')
                            ->label('Team')
                            ->options(fn () => TeamFilter::options())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->distinct(),
                        Forms\Components\Select::make('role')
                            ->label('Functie')
                            ->options(Member::TEAM_FUNCTIONS)
                            ->default(Member::ROLE_PLAYER)
                            ->required(),
                    ])
                    ->columns(2)
                    ->addActionLabel('Team toevoegen')
                    ->default([])
                    // Minimaal één team verplicht: een lid zonder team valt buiten de
                    // club-scope en gaf na opslaan een 404. Validatie blokkeert opslaan.
                    ->minItems(1)
                    ->required()
                    ->dehydrated(false)
                    ->columnSpanFull(),
            ]),

            // Alleen-lezen overzicht van ouder/verzorger-koppelingen. Alleen op de
            // bewerkpagina (bij create bestaat het record nog niet).
            Section::make('Ouder/verzorger-koppelingen')
                ->description('Met wie dit lid gekoppeld is.')
                ->schema([
                    Forms\Components\Placeholder::make('guardian_children_list')
                        ->label('Kinderen (dit lid is ouder/verzorger)')
                        ->content(fn (?Member $record): string => self::couplingSummary($record, 'children')),
                    Forms\Components\Placeholder::make('guardians_list')
                        ->label('Ouders/verzorgers (dit lid is kind)')
                        ->content(fn (?Member $record): string => self::couplingSummary($record, 'guardians')),
                ])
                ->columns(2)
                ->hiddenOn('create'),
        ]);
    }

    /**
     * Leesbare samenvatting van koppelingen voor de bewerkpagina.
     * $side: 'children' (kinderen van dit lid) of 'guardians' (ouders van dit lid).
     */
    public static function couplingSummary(?Member $record, string $side): string
    {
        if (! $record) {
            return '—';
        }

        if ($side === 'children') {
            $items = $record->guardianLinks
                ->filter(fn ($l) => $l->status !== 'revoked' && $l->child)
                ->map(fn ($l) => $l->child->name . ' — ' . self::guardianStatusLabel($l->status));
        } else {
            $items = $record->childLinks
                ->filter(fn ($l) => $l->status !== 'revoked' && $l->guardian)
                ->map(fn ($l) => $l->guardian->name . ' — ' . self::guardianStatusLabel($l->status));
        }

        return $items->isEmpty() ? '—' : $items->implode(', ');
    }

    /**
     * Slaat de "Teams & functies"-repeater op naar de member_team pivot.
     * Zet role per team en markeert (nieuwe) koppelingen als is_manual zodat de
     * Sportlink-sync ze niet loskoppelt; bestaande is_manual-vlag blijft behouden.
     * Aangeroepen vanuit Create-/EditMember (afterCreate / afterSave).
     */
    public static function syncTeamFunctions(Member $member, array $rows): void
    {
        $existing = $member->teams()->get()
            ->mapWithKeys(fn ($t) => [$t->id => (bool) $t->pivot->is_manual]);

        $sync = [];
        foreach ($rows as $row) {
            $teamId = $row['team_id'] ?? null;
            if (! $teamId) {
                continue;
            }
            $sync[$teamId] = [
                'role'      => $row['role'] ?? Member::ROLE_PLAYER,
                'is_manual' => $existing[$teamId] ?? true,
            ];
        }

        $member->teams()->sync($sync);
    }

    /**
     * Nederlandse status-labels voor een ouder/verzorger-koppeling.
     */
    public static function guardianStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'  => 'aangevraagd',
            'approved' => 'gekoppeld',
            'rejected' => 'geweigerd',
            'revoked'  => 'ingetrokken',
            default    => $status,
        };
    }

    /**
     * Bouwt losse labels op ("Kind: …", "Ouder: …") voor de personen waarmee dit
     * lid gekoppeld is. Toont actieve (approved) én lopende (pending) koppelingen;
     * geweigerde/ingetrokken worden weggelaten. Gebruikt de reeds ge-eager-loade
     * relaties (zie getEloquentQuery) om N+1 te voorkomen.
     */
    public static function couplingLabels(Member $record): array
    {
        $labels = [];

        // Dit lid is ouder/verzorger van deze kinderen.
        foreach ($record->guardianLinks as $link) {
            if (! in_array($link->status, ['approved', 'pending'], true) || ! $link->child) {
                continue;
            }
            $suffix   = $link->status === 'pending' ? ' (aangevraagd)' : '';
            $labels[] = 'Kind: ' . $link->child->name . $suffix;
        }

        // Dit lid is kind; deze personen zijn ouder/verzorger.
        foreach ($record->childLinks as $link) {
            if (! in_array($link->status, ['approved', 'pending'], true) || ! $link->guardian) {
                continue;
            }
            $suffix   = $link->status === 'pending' ? ' (aangevraagd)' : '';
            $labels[] = 'Ouder: ' . $link->guardian->name . $suffix;
        }

        return $labels;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // photoUrl() levert een volledige URL; Filament neemt die
                // ongewijzigd over. Een lid zonder foto krijgt een lege plek in
                // plaats van een gebroken plaatje.
                Tables\Columns\ImageColumn::make('foto')
                    ->label('')
                    ->circular()
                    ->getStateUsing(fn (Member $record): ?string => $record->photoUrl() ?: null),
                Tables\Columns\TextColumn::make('name')
                    ->label('Naam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('shirt_number')
                    ->label('Nr.')
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('teams.name')
                    ->label('Teams')
                    ->badge()
                    ->separator(',')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn($state) => match($state) {
                        'player'  => 'Speler',
                        'coach'   => 'Coach',
                        'medical' => 'Medische staf',
                        'staff'   => 'Overige staf',
                        default   => $state,
                    })
                    ->color(fn($state) => match($state) {
                        'coach'   => 'warning',
                        'medical' => 'danger',
                        'staff'   => 'gray',
                        default   => 'primary',
                    }),
                Tables\Columns\TextColumn::make('couplings')
                    ->label('Gekoppeld met')
                    ->badge()
                    ->getStateUsing(fn (Member $record): array => self::couplingLabels($record))
                    ->color(fn ($state): string => str_contains((string) $state, '(aangevraagd)') ? 'warning' : 'success')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('phone')->label('Mobiel')->toggleable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Bron')
                    ->badge()
                    ->getStateUsing(fn($record) => $record->external_id ? 'sync' : 'manual')
                    ->formatStateUsing(fn($state) => $state === 'sync' ? 'Sportlink' : 'Manueel')
                    ->color(fn($state) => $state === 'sync' ? 'info' : 'gray'),
                Tables\Columns\IconColumn::make('is_active')->label('Actief')->boolean(),
                Tables\Columns\TextColumn::make('last_synced_at')
                    ->label('Laatste sync')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Filters staan boven de tabel (niet in het dropdown-menu) zodat het
            // team-filter altijd in beeld is; de keuze blijft per sessie staan.
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->filters([
                Tables\Filters\SelectFilter::make('teams')
                    ->label('Team')
                    ->relationship('teams', 'name', modifyQueryUsing: fn (Builder $query) => TeamFilter::scopeQuery($query))
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->placeholder('Alle teams'),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options([
                        'player'  => 'Speler',
                        'coach'   => 'Coach',
                        'medical' => 'Medische staf',
                        'staff'   => 'Overige staf',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')->label('Actief'),
                // Verwijderde leden waren alleen via de database terug te halen.
                // Staat standaard uit, dus de lijst blijft tonen wat hij toonde.
                Tables\Filters\TrashedFilter::make()
                    ->label('Verwijderd')
                    ->placeholder('Zonder verwijderde')
                    ->trueLabel('Inclusief verwijderde')
                    ->falseLabel('Alleen verwijderde'),
            ])
            ->actions([
                Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn(Member $record): bool =>
                        !empty($record->phone)
                        && (app(WhatsAppService::class)->forClub(filament()->getTenant()?->id)->isConfigured())
                    )
                    ->form(fn(Member $record): array => [
                        Forms\Components\Placeholder::make('to')
                            ->label('Ontvanger')
                            ->content($record->name . ' (' . $record->phone . ')'),
                        Forms\Components\Textarea::make('message')
                            ->label('Bericht')
                            ->rows(4)
                            ->required(),
                    ])
                    ->action(function (Member $record, array $data): void {
                        $service = app(WhatsAppService::class)->forClub(filament()->getTenant()?->id);
                        $result  = $service->sendMessage($record->phone, $data['message']);

                        if ($result['success']) {
                            Notification::make()->success()->title('WhatsApp verstuurd')->send();
                        } else {
                            Notification::make()->danger()->title('Versturen mislukt')->body($result['error'])->send();
                        }
                    }),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
                Actions\RestoreAction::make()->label('Herstellen'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make()->label('Herstellen'),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getEloquentQuery(): Builder
    {
        // Zonder de soft-delete-scope kan het Verwijderd-filter zelf bepalen wat
        // er te zien is; staat dat filter op de standaardstand, dan filtert het
        // verwijderde leden gewoon weer weg.
        $query  = parent::getEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
        // Koppelingen (met tegenpartij) eager-loaden voor de "Gekoppeld met"-kolom.
        $query->with(['guardianLinks.child', 'childLinks.guardian']);
        $user   = auth()->user();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->whereHas('teams', fn($q) => $q->where('club_id', $tenant->id));
        }

        if (!$user || $user->isAdmin()) {
            return $query;
        }

        $teamIds = $user->managedTeamIds();
        return $query->whereHas('teams', fn($q) => $q->whereIn('teams.id', $teamIds));
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMembers::route('/'),
            'create' => Pages\CreateMember::route('/create'),
            'edit'   => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
