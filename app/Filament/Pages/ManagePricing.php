<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\PricingContent;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * De tarievenpagina beheren.
 *
 * Bedragen veranderen zonder deploy: de calculator op /tarieven rekent met
 * precies wat hier staat, dus wie het tarief bijstelt hoeft nergens anders meer
 * aan te komen.
 *
 * Alleen de super-admin. Het gaat om de prijs die alle clubs betalen, niet om
 * een instelling van één club — een clubbeheerder heeft hier niets te zoeken.
 */
class ManagePricing extends Page
{
    protected static string|\BackedEnum|null $navigationIcon   = 'heroicon-o-banknotes';
    protected static ?string $navigationLabel                  = 'Tarievenpagina';
    protected static ?string $title                            = 'Tarievenpagina';
    protected static string|\UnitEnum|null $navigationGroup     = 'Documentatie';
    protected static ?int $navigationSort                      = 92;

    protected string $view = 'filament.pages.manage-pricing';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill(PricingContent::all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Bedragen')
                    ->description('Deze bedragen staan in de kaartjes bovenaan de pagina én zijn waar de calculator mee rekent. Een komma of een euroteken mag; er wordt uit gehaald wat een getal is.')
                    ->schema([
                        TextInput::make('pricing_per_member')
                            ->label('Per clublid per jaar')
                            ->required()
                            ->prefix('€')
                            ->helperText('Ouders en verzorgers tellen niet mee; dat staat als vaste tekst bij het kaartje.'),

                        TextInput::make('pricing_setup_fee')
                            ->label('Opstartkosten (eenmalig)')
                            ->required()
                            ->prefix('€')
                            ->helperText('Voor de intake en de inrichting. Wordt alleen bij het eerste jaar opgeteld.'),

                        TextInput::make('pricing_minimum')
                            ->label('Minimale jaarbijdrage')
                            ->required()
                            ->prefix('€')
                            ->helperText('Komt het aantal leden maal het tarief hier niet aan, dan rekent de calculator dit bedrag — en legt uit waarom.'),
                    ])
                    ->columns(3),

                Section::make('Kop van de pagina')
                    ->schema([
                        TextInput::make('pricing_title')
                            ->label('Titel')
                            ->required()
                            ->maxLength(120)
                            ->columnSpanFull(),

                        Textarea::make('pricing_intro')
                            ->label('Inleiding')
                            ->rows(3)
                            ->maxLength(600)
                            ->columnSpanFull(),
                    ]),

                Section::make('Wat er allemaal in zit')
                    ->description('Eén onderdeel per regel. Ze verschijnen in twee kolommen met een vinkje ervoor.')
                    ->schema([
                        Textarea::make('pricing_includes')
                            ->label('Onderdelen')
                            ->rows(12)
                            ->columnSpanFull(),
                    ]),

                Section::make('De twee tekstblokken onderaan')
                    ->schema([
                        TextInput::make('pricing_no_hidden_title')
                            ->label('Kop van het linkerblok')
                            ->maxLength(120),

                        TextInput::make('pricing_data_title')
                            ->label('Kop van het rechterblok')
                            ->maxLength(120),

                        Textarea::make('pricing_no_hidden')
                            ->label('Geen verborgen kosten')
                            ->rows(6)
                            ->maxLength(900),

                        Textarea::make('pricing_data_note')
                            ->label('Over de dataservice')
                            ->rows(6)
                            ->maxLength(900)
                            ->helperText('Hier hoort dat de tarieven exclusief Sportlink zijn en dat het platform ook zonder koppeling werkt.'),
                    ])
                    ->columns(2),

                Section::make('Kleine lettertjes')
                    ->description('Staat er niets, dan blijft de regel weg van de pagina.')
                    ->schema([
                        Textarea::make('pricing_fine_print')
                            ->label('Voorwaarden onderaan')
                            ->rows(3)
                            ->maxLength(600)
                            ->helperText('Bijvoorbeeld de btw-vermelding of de looptijd van een overeenkomst.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        PricingContent::save($this->form->getState());

        Notification::make()
            ->title('Tarieven opgeslagen')
            ->body('De pagina op /tarieven is meteen bijgewerkt.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bekijken')
                ->label('Pagina bekijken')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(route('pricing'), shouldOpenInNewTab: true),
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
