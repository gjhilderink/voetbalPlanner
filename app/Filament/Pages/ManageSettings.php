<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\FullSyncJob;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Services\SportlinkMcpService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ManageSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Instellingen';
    protected static ?string $title = 'Sportlink MCP Instellingen';
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'mcp_enabled'  => filter_var(Setting::get('mcp_enabled', false), FILTER_VALIDATE_BOOLEAN),
            'mcp_base_url' => Setting::get('mcp_base_url', ''),
            'mcp_api_key'  => Setting::get('mcp_api_key', ''),
            'mcp_timeout'  => (int) Setting::get('mcp_timeout', 30),
            'mcp_club_id'  => Setting::get('mcp_club_id', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Verbindingsinstellingen')
                    ->description('Configureer de verbinding met de Sportlink MCP API.')
                    ->schema([
                        Toggle::make('mcp_enabled')
                            ->label('MCP synchronisatie ingeschakeld')
                            ->helperText('Schakel automatische synchronisatie van teams, leden en wedstrijden in of uit.')
                            ->columnSpanFull(),
                        TextInput::make('mcp_base_url')
                            ->label('API basis-URL')
                            ->placeholder('https://api.sportlink.com/v1')
                            ->url()
                            ->required()
                            ->maxLength(500)
                            ->helperText('De basis-URL van de Sportlink MCP API.'),
                        TextInput::make('mcp_api_key')
                            ->label('API sleutel')
                            ->placeholder('sk-...')
                            ->password()
                            ->revealable()
                            ->required()
                            ->maxLength(500)
                            ->helperText('De geheime API sleutel. Wordt versleuteld opgeslagen.'),
                        TextInput::make('mcp_club_id')
                            ->label('Club ID')
                            ->placeholder('CLB-12345')
                            ->maxLength(100)
                            ->helperText('Het externe club-ID in Sportlink (optioneel).'),
                        TextInput::make('mcp_timeout')
                            ->label('Timeout (seconden)')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(120)
                            ->default(30)
                            ->helperText('Maximale wachttijd per API-aanvraag.'),
                    ])
                    ->columns(2),

                Section::make('Synchronisatiestatus')
                    ->schema([
                        Placeholder::make('last_sync')
                            ->label('Laatste synchronisatie')
                            ->content(function (): string {
                                $log = SyncLog::where('status', 'completed')->latest()->first();
                                if (!$log) {
                                    return 'Nog niet gesynchroniseerd';
                                }
                                return $log->completed_at->format('d-m-Y H:i:s')
                                    . ' (' . $log->records_synced . ' records)';
                            }),
                        Placeholder::make('mcp_status')
                            ->label('Verbindingsstatus')
                            ->content(function (): string {
                                if (!Setting::get('mcp_base_url') || !Setting::get('mcp_api_key')) {
                                    return 'Niet geconfigureerd';
                                }
                                return 'Geconfigureerd';
                            }),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('mcp_enabled', $data['mcp_enabled'] ? '1' : '0', 'mcp');
        Setting::set('mcp_base_url', $data['mcp_base_url'], 'mcp');
        Setting::set('mcp_api_key', $data['mcp_api_key'], 'mcp', true);
        Setting::set('mcp_club_id', $data['mcp_club_id'] ?? '', 'mcp');
        Setting::set('mcp_timeout', (string) $data['mcp_timeout'], 'mcp');

        Notification::make()
            ->success()
            ->title('Instellingen opgeslagen')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test verbinding')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (): void {
                    $service = app(SportlinkMcpService::class);

                    if (!$service->isConfigured()) {
                        Notification::make()
                            ->warning()
                            ->title('Niet geconfigureerd')
                            ->body('Sla eerst de URL en API sleutel op.')
                            ->send();
                        return;
                    }

                    $result = $service->healthCheck();

                    if ($result['connected']) {
                        Notification::make()
                            ->success()
                            ->title('Verbinding geslaagd')
                            ->body($result['message'])
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Verbinding mislukt')
                            ->body($result['message'])
                            ->send();
                    }
                }),

            Action::make('syncNow')
                ->label('Nu synchroniseren')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Synchronisatie starten')
                ->modalDescription('Dit start een volledige synchronisatie van teams, leden en wedstrijden vanuit Sportlink. Dit kan enkele minuten duren.')
                ->modalSubmitActionLabel('Starten')
                ->action(function (): void {
                    $service = app(SportlinkMcpService::class);

                    if (!$service->isConfigured()) {
                        Notification::make()
                            ->warning()
                            ->title('Niet geconfigureerd')
                            ->body('Configureer eerst de MCP verbinding.')
                            ->send();
                        return;
                    }

                    FullSyncJob::dispatch();

                    Notification::make()
                        ->success()
                        ->title('Synchronisatie gestart')
                        ->body('De synchronisatie is in de wachtrij geplaatst.')
                        ->send();
                }),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Opslaan')
                            ->submit('save')
                            ->keyBindings(['mod+s']),
                    ])->key('form-actions'),
                ]),
        ]);
    }
}
