<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\DemoRequestResource\Pages;
use App\Models\DemoRequest;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DemoRequestResource extends Resource
{
    protected static ?string $model = DemoRequest::class;
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static ?string $navigationLabel = 'Demo-aanvragen';
    protected static ?string $modelLabel = 'Demo-aanvraag';
    protected static ?string $pluralModelLabel = 'Demo-aanvragen';
    protected static ?int $navigationSort = 8;
    protected static bool $isScopedToTenant = false;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /**
     * Het aantal openstaande aanvragen in de navigatie.
     *
     * Een demo-aanvraag is tijdgevoelig: wie een week later wordt teruggebeld
     * heeft meestal al ergens anders gekeken. Daarom telt hij mee in het menu.
     */
    public static function getNavigationBadge(): ?string
    {
        $aantal = static::getModel()::where('status', 'pending')->count();

        return $aantal > 0 ? (string) $aantal : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Aanvraag')->schema([
                Forms\Components\TextInput::make('club_name')
                    ->label('Clubnaam')
                    ->disabled()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('contact_name')
                    ->label('Contactpersoon')
                    ->disabled(),
                Forms\Components\TextInput::make('email')
                    ->label('E-mail')
                    ->disabled(),
                Forms\Components\TextInput::make('phone')
                    ->label('Telefoon')
                    ->placeholder('Niet opgegeven')
                    ->disabled(),
                Forms\Components\TextInput::make('member_count')
                    ->label('Aantal leden')
                    ->placeholder('Niet opgegeven')
                    ->disabled(),
            ])->columns(2),

            Section::make('Opmerkingen aanvrager')->schema([
                Forms\Components\Textarea::make('notes')
                    ->label('Waar wil de club het over hebben?')
                    ->placeholder('Niets opgegeven')
                    ->disabled()
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Section::make('Opvolging')->schema([
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options(DemoRequest::STATUSSEN)
                    ->required(),
                Forms\Components\Textarea::make('admin_notes')
                    ->label('Interne notities')
                    ->rows(3)
                    ->helperText('Alleen zichtbaar voor beheerders.')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('club_name')
                    ->label('Club')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('contact_name')
                    ->label('Contactpersoon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefoon')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('member_count')
                    ->label('Leden')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'info'    => 'scheduled',
                        'success' => 'completed',
                        'danger'  => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => DemoRequest::STATUSSEN[$state] ?? $state),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aangevraagd')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(DemoRequest::STATUSSEN),
            ])
            ->actions([
                Actions\EditAction::make()->label('Opvolgen'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDemoRequests::route('/'),
            'edit'  => Pages\EditDemoRequest::route('/{record}/edit'),
        ];
    }
}
