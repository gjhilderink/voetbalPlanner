<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Jobs\FullSyncJob;
use App\Models\Setting;
use App\Models\SyncLog;
use App\Services\MatchSyncService;
use App\Services\MemberSyncService;
use App\Services\SportlinkMcpService;
use App\Services\TeamSyncService;
use App\Services\WhatsAppService;
use Illuminate\Support\HtmlString;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
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
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationLabel = 'Instellingen';
    protected static ?string $title = 'Sportlink MCP Instellingen';
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.manage-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $club   = filament()->getTenant();
        $clubId = $club?->id;

        $this->form->fill([
            'registration_notification_email'    => Setting::get('registration_notification_email', '', null),
            'registration_notification_subject'  => Setting::get('registration_notification_subject', '', null),
            'smtp_host'         => Setting::get('smtp_host', config('mail.mailers.smtp.host', ''), null),
            'smtp_port'         => Setting::get('smtp_port', config('mail.mailers.smtp.port', '587'), null),
            'smtp_encryption'   => Setting::get('smtp_encryption', config('mail.mailers.smtp.encryption', 'tls'), null),
            'smtp_username'     => Setting::get('smtp_username', config('mail.mailers.smtp.username', ''), null),
            'smtp_password'     => Setting::get('smtp_password', '', null),
            'smtp_from_address' => Setting::get('smtp_from_address', config('mail.from.address', ''), null),
            'smtp_from_name'    => Setting::get('smtp_from_name', config('mail.from.name', ''), null),
            'mcp_enabled'         => filter_var(Setting::get('mcp_enabled', false, $clubId), FILTER_VALIDATE_BOOLEAN),
            'mcp_base_url'        => Setting::get('mcp_base_url', '', $clubId),
            'mcp_api_key'         => Setting::get('mcp_api_key', '', $clubId),
            'mcp_timeout'         => (int) Setting::get('mcp_timeout', 30, $clubId),
            'mcp_club_id'         => Setting::get('mcp_club_id', '', $clubId),
            'whatsapp_enabled'    => filter_var(Setting::get('whatsapp_enabled', false, $clubId), FILTER_VALIDATE_BOOLEAN),
            'whatsapp_bridge_url' => Setting::get('whatsapp_bridge_url', 'https://mcp.nubixhosting.nl/mcp/whatsapp/mcp', $clubId),
            'whatsapp_api_key'    => Setting::get('whatsapp_api_key', '', $clubId),
            'debug_enabled'       => filter_var(Setting::get('debug_enabled', false, $clubId), FILTER_VALIDATE_BOOLEAN),
            'club_name'           => $club?->name,
            'club_address'        => $club?->address,
            'club_city'           => $club?->city,
            'club_phone'          => $club?->phone,
            'club_email'          => $club?->email,
            'club_website'        => $club?->website,
            'club_logo_path'      => $club?->logo_path,
            'primary_color'       => $club?->primary_color ?? '#1e3a5f',
            'secondary_color'     => $club?->secondary_color ?? '#3b82f6',
            'accent_color'        => $club?->accent_color ?? '#10b981',
            'email_header_text'   => $club?->email_header_text,
            'email_intro_text'    => $club?->email_intro_text,
            'email_footer_text'   => $club?->email_footer_text,
            'email_subject'       => $club?->email_subject,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Systeeminstellingen')
                    ->description('Globale instellingen voor de hele applicatie (alleen zichtbaar voor super-admins).')
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false)
                    ->schema([
                        TextInput::make('registration_notification_email')
                            ->label('E-mail voor clubaanvragen')
                            ->email()
                            ->maxLength(255)
                            ->helperText('Naar dit adres wordt een melding gestuurd bij elke nieuwe clubaanvraag via de landingspagina.'),
                        TextInput::make('registration_notification_subject')
                            ->label('Onderwerp van de notificatiemail')
                            ->maxLength(255)
                            ->placeholder('Nieuwe clubaanvraag: {club_naam}')
                            ->helperText('Gebruik {club_naam} als variabele. Leeg = standaard onderwerp.'),
                    ]),

                Section::make('E-mail / SMTP')
                    ->description('SMTP-instellingen voor systeemmails (wachtwoord vergeten, notificaties).')
                    ->visible(fn() => auth()->user()?->hasRole('super_admin') ?? false)
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('SMTP host')
                            ->placeholder('smtp.example.com')
                            ->maxLength(255),
                        TextInput::make('smtp_port')
                            ->label('Poort')
                            ->placeholder('587')
                            ->numeric(),
                        Forms\Components\Select::make('smtp_encryption')
                            ->label('Encryptie')
                            ->options([
                                'tls'  => 'TLS (STARTTLS, poort 587)',
                                'ssl'  => 'SSL (poort 465)',
                                ''     => 'Geen',
                            ])
                            ->default('tls'),
                        TextInput::make('smtp_username')
                            ->label('Gebruikersnaam')
                            ->maxLength(255),
                        TextInput::make('smtp_password')
                            ->label('Wachtwoord')
                            ->password()
                            ->revealable(),
                        TextInput::make('smtp_from_address')
                            ->label('Afzender e-mailadres')
                            ->email()
                            ->placeholder('noreply@jouwclub.nl')
                            ->maxLength(255),
                        TextInput::make('smtp_from_name')
                            ->label('Afzender naam')
                            ->placeholder('VoetbalPlanner')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Club informatie')
                    ->description('Basisinformatie over de club.')
                    ->schema([
                        TextInput::make('club_name')
                            ->label('Clubnaam')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Placeholder::make('logo_current')
                            ->label('Huidig logo')
                            ->content(function (): HtmlString|string {
                                $path = filament()->getTenant()?->logo_path;
                                if (!$path) return 'Geen logo ingesteld.';
                                $url = asset('logos/' . basename($path));
                                return new HtmlString(
                                    '<img src="' . $url . '"'
                                    . ' alt="Logo" style="display:block;max-height:100px;max-width:260px;'
                                    . 'width:auto;height:auto;object-fit:contain;'
                                    . 'border-radius:6px;border:1px solid #e5e7eb;padding:6px;background:#fff;">'
                                );
                            })
                            ->columnSpanFull(),
                        FileUpload::make('club_logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('logos')
                            ->imagePreviewHeight('80')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        TextInput::make('club_address')
                            ->label('Adres')
                            ->maxLength(500),
                        TextInput::make('club_city')
                            ->label('Woonplaats')
                            ->maxLength(100),
                        TextInput::make('club_phone')
                            ->label('Telefoon')
                            ->tel()
                            ->maxLength(30),
                        TextInput::make('club_email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('club_website')
                            ->label('Website')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('Huisstijl')
                    ->description('Kies de kleuren en e-mailopmaak die bij de club passen.')
                    ->schema([
                        ColorPicker::make('primary_color')
                            ->label('Primaire kleur')
                            ->hex()
                            ->helperText('Hoofdkleur — knoppen, menu-accenten, e-mailkop.'),
                        ColorPicker::make('secondary_color')
                            ->label('Secundaire kleur')
                            ->hex()
                            ->helperText('Tweede kleur — kaartachtergronden, subtiele elementen.'),
                        ColorPicker::make('accent_color')
                            ->label('Accentkleur')
                            ->hex()
                            ->helperText('Derde kleur — highlights, badges, call-to-action.'),
                        TextInput::make('email_subject')
                            ->label('E-mail onderwerp')
                            ->placeholder('Jouw inloglink voor {club_naam}')
                            ->maxLength(255)
                            ->helperText('Onderwerpregel van de inlogmail (leeg = standaard). '
                                . 'Variabelen: {club_naam}, {ontvanger_naam}, {app_naam}.'),
                        TextInput::make('email_header_text')
                            ->label('E-mail koptekst')
                            ->placeholder(config('app.name'))
                            ->maxLength(255)
                            ->helperText('Tekst in de kleurenbalk bovenaan de e-mail (leeg = clubnaam).'),
                        Textarea::make('email_intro_text')
                            ->label('E-mail intro')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Inleidende tekst in de e-mail (leeg = standaard).'),
                        TextInput::make('email_footer_text')
                            ->label('E-mail voettekst')
                            ->maxLength(255)
                            ->helperText('Tekst onderaan de e-mail (leeg = standaard).'),
                    ])
                    ->columns(2),

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

                Section::make('WhatsApp')
                    ->description('Verstuur WhatsApp berichten via de Nubix WhatsApp bridge.')
                    ->schema([
                        Toggle::make('whatsapp_enabled')
                            ->label('WhatsApp ingeschakeld')
                            ->helperText('Schakel WhatsApp berichten in voor deze club.')
                            ->columnSpanFull(),
                        TextInput::make('whatsapp_bridge_url')
                            ->label('Bridge URL')
                            ->placeholder('https://mcp.nubixhosting.nl/mcp/whatsapp/mcp')
                            ->url()
                            ->maxLength(500)
                            ->helperText('MCP endpoint van de WhatsApp bridge.')
                            ->columnSpanFull(),
                        TextInput::make('whatsapp_api_key')
                            ->label('API sleutel (Bearer token)')
                            ->placeholder('nubix_...')
                            ->password()
                            ->revealable()
                            ->maxLength(500)
                            ->helperText('De Bearer token voor de Nubix WhatsApp bridge.')
                            ->columnSpanFull(),
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
                                $clubId = filament()->getTenant()?->id;
                                if (!Setting::get('mcp_base_url', null, $clubId) || !Setting::get('mcp_api_key', null, $clubId)) {
                                    return 'Niet geconfigureerd';
                                }
                                return 'Geconfigureerd';
                            }),
                    ])
                    ->columns(2),

                Section::make('Ontwikkelaarsopties')
                    ->schema([
                        Toggle::make('debug_enabled')
                            ->label('Debug modus inschakelen')
                            ->helperText('Toont de Debug API en Debug WhatsApp knoppen in de header.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data   = $this->form->getState();
        $clubId = filament()->getTenant()?->id;

        if (auth()->user()?->hasRole('super_admin')) {
            Setting::set('registration_notification_email',   $data['registration_notification_email'] ?? '', 'system', false, null);
            Setting::set('registration_notification_subject', $data['registration_notification_subject'] ?? '', 'system', false, null);
            Setting::set('smtp_host',         $data['smtp_host'] ?? '', 'smtp', false, null);
            Setting::set('smtp_port',         $data['smtp_port'] ?? '587', 'smtp', false, null);
            Setting::set('smtp_encryption',   $data['smtp_encryption'] ?? 'tls', 'smtp', false, null);
            Setting::set('smtp_username',     $data['smtp_username'] ?? '', 'smtp', false, null);
            Setting::set('smtp_password',     $data['smtp_password'] ?? '', 'smtp', true, null);
            Setting::set('smtp_from_address', $data['smtp_from_address'] ?? '', 'smtp', false, null);
            Setting::set('smtp_from_name',    $data['smtp_from_name'] ?? '', 'smtp', false, null);
        }

        Setting::set('mcp_enabled', $data['mcp_enabled'] ? '1' : '0', 'mcp', false, $clubId);
        Setting::set('mcp_base_url', $data['mcp_base_url'], 'mcp', false, $clubId);
        Setting::set('mcp_api_key', $data['mcp_api_key'], 'mcp', true, $clubId);
        Setting::set('mcp_club_id', $data['mcp_club_id'] ?? '', 'mcp', false, $clubId);
        Setting::set('mcp_timeout', (string) $data['mcp_timeout'], 'mcp', false, $clubId);

        Setting::set('whatsapp_enabled',    $data['whatsapp_enabled'] ? '1' : '0', 'whatsapp', false, $clubId);
        Setting::set('whatsapp_bridge_url', $data['whatsapp_bridge_url'] ?? '', 'whatsapp', false, $clubId);
        Setting::set('whatsapp_api_key',    $data['whatsapp_api_key'] ?? '', 'whatsapp', true, $clubId);

        Setting::set('debug_enabled', $data['debug_enabled'] ? '1' : '0', 'app', false, $clubId);

        $club = filament()->getTenant();
        if ($club) {
            $club->update([
                'name'               => $data['club_name'] ?? $club->name,
                'address'            => $data['club_address'] ?? null,
                'city'               => $data['club_city'] ?? null,
                'phone'              => $data['club_phone'] ?? null,
                'email'              => $data['club_email'] ?? null,
                'website'            => $data['club_website'] ?? null,
                'logo_path'          => $data['club_logo_path'] ?? $club->logo_path,
                'primary_color'      => $data['primary_color'] ?? $club->primary_color,
                'secondary_color'    => $data['secondary_color'] ?? $club->secondary_color,
                'accent_color'       => $data['accent_color'] ?? $club->accent_color,
                'email_header_text'  => $data['email_header_text'] ?? null,
                'email_intro_text'   => $data['email_intro_text'] ?? null,
                'email_footer_text'  => $data['email_footer_text'] ?? null,
                'email_subject'      => $data['email_subject'] ?? null,
            ]);
        }

        Notification::make()
            ->success()
            ->title('Instellingen opgeslagen')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('debugWhatsApp')
                ->label('Debug WhatsApp')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->visible(fn() => filter_var(Setting::get('debug_enabled', false, filament()->getTenant()?->id), FILTER_VALIDATE_BOOLEAN))
                ->modalHeading('WhatsApp bridge — tools/list response')
                ->modalContent(function (): HtmlString {
                    $result = app(WhatsAppService::class)->forClub(filament()->getTenant()?->id)->discoverTools();
                    $json   = htmlspecialchars(
                        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                    return new HtmlString(
                        '<p style="font-size:11px;color:#6b7280;margin-bottom:8px">'
                        . 'Status: <strong>' . ($result['response_status'] ?? '?') . '</strong>'
                        . ' &nbsp;|&nbsp; Content-Type: <strong>' . htmlspecialchars($result['response_type'] ?? '') . '</strong>'
                        . '</p>'
                        . '<pre style="font-size:12px;background:#f3f4f6;padding:1rem;border-radius:6px;overflow:auto;max-height:70vh;white-space:pre-wrap;word-break:break-all">'
                        . $json . '</pre>'
                    );
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Sluiten'),

            Action::make('testWhatsApp')
                ->label('Test WhatsApp')
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('test_phone')
                        ->label('Telefoonnummer (incl. landcode)')
                        ->placeholder('+31612345678')
                        ->tel()
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('test_message')
                        ->label('Testbericht')
                        ->default('Hallo! Dit is een testbericht vanuit VoetbalPlanner.')
                        ->rows(3)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $service = app(WhatsAppService::class)->forClub(filament()->getTenant()?->id);

                    if (!$service->isConfigured()) {
                        Notification::make()
                            ->warning()
                            ->title('WhatsApp niet geconfigureerd')
                            ->body('Sla eerst de API sleutel op en schakel WhatsApp in.')
                            ->send();
                        return;
                    }

                    $result = $service->sendMessage($data['test_phone'], $data['test_message']);

                    if ($result['success']) {
                        Notification::make()
                            ->success()
                            ->title('Bericht verzonden')
                            ->body('Het testbericht is verstuurd naar ' . $data['test_phone'])
                            ->send();
                    } else {
                        Notification::make()
                            ->danger()
                            ->title('Versturen mislukt')
                            ->body($result['error'])
                            ->send();
                    }
                }),

            Action::make('testConnection')
                ->label('Test verbinding')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function (): void {
                    $service = app(SportlinkMcpService::class)->forClub(filament()->getTenant()?->id);

                    if (!$service->isConfigured()) {
                        Notification::make()
                            ->warning()
                            ->title('Niet geconfigureerd')
                            ->body('Sla eerst de URL en API sleutel op.')
                            ->send();
                        return;
                    }

                    $result = $service->healthCheck();

                    $authFailed = in_array($result['status'], [401, 403]);
                    if ($result['connected'] && !$authFailed) {
                        Notification::make()
                            ->success()
                            ->title('Verbinding geslaagd')
                            ->body($result['message'])
                            ->send();
                    } elseif ($result['connected']) {
                        Notification::make()
                            ->warning()
                            ->title('Authenticatie mislukt')
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

            Action::make('debugApi')
                ->label('Debug API')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->visible(fn() => filter_var(Setting::get('debug_enabled', false, filament()->getTenant()?->id), FILTER_VALIDATE_BOOLEAN))
                ->modalHeading('Raw API response')
                ->modalContent(function (): HtmlString {
                    $result = app(SportlinkMcpService::class)->forClub(filament()->getTenant()?->id)->discoverApi();
                    $json = htmlspecialchars(
                        json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    );
                    return new HtmlString(
                        '<pre style="font-size:12px;background:#f3f4f6;padding:1rem;border-radius:6px;overflow:auto;max-height:70vh;white-space:pre-wrap;word-break:break-all">'
                        . $json . '</pre>'
                    );
                })
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Sluiten'),

            Action::make('syncNow')
                ->label('Nu synchroniseren')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Synchronisatie starten')
                ->modalDescription('Dit start een volledige synchronisatie van teams, leden en wedstrijden vanuit Sportlink. Dit kan enkele minuten duren.')
                ->modalSubmitActionLabel('Starten')
                ->action(function (): void {
                    $service = app(SportlinkMcpService::class)->forClub(filament()->getTenant()?->id);

                    if (!$service->isConfigured()) {
                        Notification::make()
                            ->warning()
                            ->title('Niet geconfigureerd')
                            ->body('Configureer eerst de URL en API sleutel.')
                            ->send();
                        return;
                    }

                    $errors = [];
                    $totals = [];
                    $clubId = filament()->getTenant()?->id;

                    try {
                        $log = app(TeamSyncService::class)->forClub($clubId)->sync();
                        $totals[] = $log->records_synced . ' teams';
                        if ($log->status === 'failed') {
                            $errors[] = 'Teams: ' . $log->error_message;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = 'Teams: ' . $e->getMessage();
                    }

                    try {
                        $log = app(MemberSyncService::class)->forClub($clubId)->sync();
                        $totals[] = $log->records_synced . ' leden';
                        if ($log->status === 'failed') {
                            $errors[] = 'Leden: ' . $log->error_message;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = 'Leden: ' . $e->getMessage();
                    }

                    try {
                        $log = app(MatchSyncService::class)->forClub($clubId)->sync();
                        $totals[] = $log->records_synced . ' wedstrijden';
                        if ($log->status === 'failed') {
                            $errors[] = 'Wedstrijden: ' . $log->error_message;
                        }
                    } catch (\Throwable $e) {
                        $errors[] = 'Wedstrijden: ' . $e->getMessage();
                    }

                    if (empty($errors)) {
                        Notification::make()
                            ->success()
                            ->title('Synchronisatie voltooid')
                            ->body('Gesynchroniseerd: ' . implode(', ', $totals) . '.')
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title('Synchronisatie gedeeltelijk mislukt')
                            ->body(implode("\n", $errors))
                            ->send();
                    }
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
