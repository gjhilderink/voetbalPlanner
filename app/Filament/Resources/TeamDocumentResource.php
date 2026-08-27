<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\TeamDocumentResource\Pages;
use App\Filament\Support\TeamFilter;
use App\Models\TeamDocument;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Documenten die aan een elftal of aan de hele club hangen: spelregels,
 * formulieren, draaiboeken.
 *
 * Beheer zit hier en niet in de app: uploaden en ordenen doe je achter een
 * toetsenbord, en dan staat alles op één plek waar je het ook weer weghaalt.
 */
class TeamDocumentResource extends Resource
{
    protected static ?string $model = TeamDocument::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel                = 'Documenten';
    protected static ?string $modelLabel                     = 'Document';
    protected static ?string $pluralModelLabel               = 'Documenten';
    protected static ?int    $navigationSort                 = 7;
    protected static bool    $isScopedToTenant               = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'club_admin', 'coach']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        $query  = parent::getEloquentQuery();
        $tenant = filament()->getTenant();

        if ($tenant) {
            $query->where('club_id', $tenant->id);
        }

        $user = auth()->user();

        // Een coach ziet de documenten van zijn eigen elftallen, plus die van de
        // hele club — die gaan hem net zo goed aan.
        if ($user && ! $user->isAdmin()) {
            $teamIds = $user->managedTeamIds();
            $query->where(fn ($q) => $q->whereIn('team_id', $teamIds)->orWhereNull('team_id'));
        }

        return $query->with('team');
    }

    public static function form(Schema $schema): Schema
    {
        $clubId = fn () => filament()->getTenant()?->id ?? auth()->user()?->club_id;

        return $schema->components([
            Section::make('Document')->columns(2)->schema([
                Forms\Components\Hidden::make('club_id')->default($clubId),
                Forms\Components\Hidden::make('uploaded_by')->default(fn () => auth()->id()),

                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Wat er in de app en in de lijst komt te staan.'),

                Forms\Components\TextInput::make('description')
                    ->label('Korte toelichting')
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->placeholder('Bijvoorbeeld: geldig vanaf 1 januari'),

                Forms\Components\Select::make('team_id')
                    ->label('Elftal')
                    ->options(fn () => TeamFilter::options())
                    ->searchable()
                    ->preload()
                    ->placeholder('Hele club')
                    ->helperText('Leeg laten betekent: zichtbaar voor de hele club.')
                    ->columnSpan(1),

                Forms\Components\TextInput::make('sort_order')
                    ->label('Volgorde')
                    ->numeric()
                    ->default(0)
                    ->helperText('Laag getal staat bovenaan.')
                    ->columnSpan(1),

                // De bestandsnaam op schijf wordt willekeurig; zie het model.
                // Daarom original_name apart bewaren, anders staat er in de app
                // een reeks tekens in plaats van een naam.
                Forms\Components\FileUpload::make('file_path')
                    ->label('Bestand')
                    ->required()
                    ->disk(TeamDocument::DISK)
                    ->acceptedFileTypes(TeamDocument::TOEGESTAAN)
                    ->maxSize(20480)
                    ->storeFileNamesIn('original_name')
                    ->helperText('PDF, Word of Excel, maximaal 20 MB.')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Zichtbaar in de app')
                    ->default(true)
                    ->columnSpan(1),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->searchable()
                    ->sortable()
                    ->description(fn (TeamDocument $record): ?string => $record->description),

                Tables\Columns\TextColumn::make('team.name')
                    ->label('Elftal')
                    ->badge()
                    ->placeholder('Hele club')
                    ->sortable(),

                Tables\Columns\TextColumn::make('original_name')
                    ->label('Bestand')
                    ->searchable()
                    ->limit(32)
                    ->description(fn (TeamDocument $record): string => trim(
                        strtoupper($record->extension()) . ' · ' . $record->sizeLabel(),
                        ' ·'
                    )),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Zichtbaar')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Toegevoegd')
                    ->dateTime('d-m-Y')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                // Op team_id met een vaste optielijst, niet via relationship():
                // die laatste kon de relatie hier niet oplossen en gaf een 500 op
                // de hele pagina. TeamFilter::options() bestaat precies hiervoor,
                // en de twee andere resources die op een belongsTo-elftal filteren
                // (bardiensten, stafgroepen) doen het net zo.
                Tables\Filters\SelectFilter::make('team_id')
                    ->label('Elftal')
                    ->options(fn (): array => TeamFilter::options())
                    ->searchable()
                    ->placeholder('Alle'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Zichtbaar'),
            ])
            ->actions([
                // De deelbare link. Werkt zonder inloggen, dus je kunt hem in een
                // appbericht of een mail plakken.
                Actions\Action::make('kopieer')
                    ->label('Link kopiëren')
                    ->icon('heroicon-o-link')
                    ->color('gray')
                    ->action(function (TeamDocument $record): void {
                        Notification::make()
                            ->success()
                            ->title('Deelbare link')
                            ->body($record->url())
                            ->persistent()
                            ->send();
                    }),
                Actions\Action::make('openen')
                    ->label('Openen')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (TeamDocument $record): string => $record->url())
                    ->openUrlInNewTab(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canViewAny();
    }

    /**
     * Vult mime_type en size aan de hand van het geüploade bestand.
     *
     * FileUpload levert alleen het pad en, via storeFileNamesIn, de
     * oorspronkelijke naam. De rest halen we hier van schijf: in de app is een
     * bestandsgrootte niet meer op te vragen zonder het hele document te
     * downloaden, en dan staat er dus niets bij.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function vulBestandsgegevens(array $data): array
    {
        $pad = $data['file_path'] ?? null;

        if (! is_string($pad) || $pad === '') {
            return $data;
        }

        $disk = Storage::disk(TeamDocument::DISK);

        if (! $disk->exists($pad)) {
            return $data;
        }

        $data['size']      = $disk->size($pad);
        $data['mime_type'] = $disk->mimeType($pad) ?: null;

        // Geen oorspronkelijke naam meegekomen? Dan de bestandsnaam zelf, zodat
        // er in de app in elk geval iets leesbaars staat.
        if (empty($data['original_name'])) {
            $data['original_name'] = basename($pad);
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTeamDocuments::route('/'),
            'create' => Pages\CreateTeamDocument::route('/create'),
            'edit'   => Pages\EditTeamDocument::route('/{record}/edit'),
        ];
    }
}
