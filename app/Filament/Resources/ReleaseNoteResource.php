<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ReleaseNoteResource\Pages;
use App\Mail\ReleaseNoteMail;
use App\Models\Member;
use App\Models\ReleaseNote;
use App\Models\User;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReleaseNoteResource extends Resource
{
    protected static ?string $model = ReleaseNote::class;
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel                  = 'Release notes';
    protected static ?string $modelLabel                       = 'Release note';
    protected static ?string $pluralModelLabel                 = 'Release notes';
    protected static string|\UnitEnum|null $navigationGroup    = 'Features & Releases';
    protected static ?int    $navigationSort                   = 20;
    protected static bool    $isScopedToTenant                 = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    // Release notes worden automatisch gegenereerd uit features (status
    // "Uitgebracht"); handmatig aanmaken is daarom uitgeschakeld.
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Titel')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Forms\Components\DateTimePicker::make('released_at')
                    ->label('Uitgebracht op')
                    ->seconds(false)
                    ->columnSpanFull(),

                Forms\Components\RichEditor::make('body')
                    ->label('Inhoud')
                    ->helperText('Automatisch overgenomen uit de feature; pas gerust aan voor de publicatie.')
                    ->columnSpanFull(),
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
                    ->wrap(),

                Tables\Columns\TextColumn::make('feature.status')
                    ->label('Feature-status')
                    ->badge()
                    ->formatStateUsing(fn($state) => \App\Models\Feature::$statusLabels[$state] ?? ($state ?? '—'))
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('released_at')
                    ->label('Uitgebracht')
                    ->dateTime('d-m-Y')
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->defaultSort('released_at', 'desc')
            ->actions([
                Actions\Action::make('mail')
                    ->label('Mailen')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false)
                    ->modalHeading('Release note mailen')
                    ->modalSubmitActionLabel('Versturen')
                    ->form([
                        Forms\Components\Radio::make('scope')
                            ->label('Naar wie versturen?')
                            ->options([
                                'self' => 'Test naar mezelf',
                                'all'  => 'Alle leden & gebruikers met e-mailadres',
                            ])
                            ->descriptions([
                                'self' => 'Stuurt de release note alleen naar je eigen e-mailadres.',
                                'all'  => 'Stuurt naar alle actieve leden en gebruikers van deze club met een e-mailadres.',
                            ])
                            ->default('self')
                            ->required(),
                    ])
                    ->action(fn(ReleaseNote $record, array $data) => static::mailReleaseNote($record, $data['scope'] ?? 'self')),

                Actions\EditAction::make()
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false),
                Actions\DeleteAction::make()
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false),
                ]),
            ]);
    }

    /**
     * Verstuurt een release note per e-mail. scope 'self' = alleen naar de
     * ingelogde beheerder (test); scope 'all' = alle actieve leden + gebruikers
     * van de huidige club met een e-mailadres (gededupliceerd, in BCC-batches).
     */
    protected static function mailReleaseNote(ReleaseNote $record, string $scope): void
    {
        $club = filament()->getTenant();

        // Testmail naar de beheerder zelf.
        if ($scope === 'self') {
            $to = auth()->user()?->email;
            if (! $to) {
                Notification::make()->danger()->title('Je account heeft geen e-mailadres.')->send();
                return;
            }
            try {
                Mail::to($to)->send(new ReleaseNoteMail($record, $club));
                Notification::make()->success()->title('Testmail verstuurd naar ' . $to)->send();
            } catch (\Throwable $e) {
                Notification::make()->danger()->title('Versturen mislukt')->body($e->getMessage())->send();
            }
            return;
        }

        // Alle ontvangers: actieve gebruikers + leden van de club met e-mailadres.
        $userQuery = User::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');
        if ($club) {
            $userQuery->where('club_id', $club->id);
        }

        $memberQuery = Member::query()
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '!=', '');
        if ($club) {
            $memberQuery->whereHas('teams', fn($q) => $q->where('teams.club_id', $club->id));
        }

        $emails = $userQuery->pluck('email')
            ->merge($memberQuery->pluck('email'))
            ->map(fn($e) => strtolower(trim($e)))
            ->filter()
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            Notification::make()->warning()->title('Geen ontvangers met een e-mailadres gevonden.')->send();
            return;
        }

        // BCC-batches met een geldige To-header (afzender/club-adres) zodat SMTP
        // de mail niet weigert en de ontvangers elkaars adres niet zien.
        $toAddress = config('mail.from.address') ?: auth()->user()?->email;

        $sent = 0;
        $failed = 0;
        foreach ($emails->chunk(50) as $chunk) {
            try {
                Mail::to($toAddress)->bcc($chunk->all())->send(new ReleaseNoteMail($record, $club));
                $sent += $chunk->count();
            } catch (\Throwable $e) {
                $failed += $chunk->count();
                Log::error('[ReleaseNoteMail] batch mislukt', ['error' => $e->getMessage()]);
            }
        }

        if ($failed === 0) {
            Notification::make()->success()->title("Release note verstuurd naar {$sent} ontvanger(s).")->send();
        } else {
            Notification::make()->warning()
                ->title("Verstuurd: {$sent}, mislukt: {$failed}")
                ->body('Zie de logs voor details.')
                ->send();
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReleaseNotes::route('/'),
            'edit'  => Pages\EditReleaseNote::route('/{record}/edit'),
        ];
    }
}
