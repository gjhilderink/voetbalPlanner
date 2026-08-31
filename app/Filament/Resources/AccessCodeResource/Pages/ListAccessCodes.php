<?php

declare(strict_types=1);

namespace App\Filament\Resources\AccessCodeResource\Pages;

use App\Filament\Resources\AccessCodeResource;
use App\Filament\Support\ImportNotifier;
use App\Imports\AccessCodesImport;
use App\Models\AccessCode;
use App\Models\AgendaItem;
use App\Support\Qr;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ListAccessCodes extends ListRecords
{
    protected static string $resource = AccessCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('genereren')
                ->label('Codes genereren')
                ->icon('heroicon-o-sparkles')
                ->visible(fn (): bool => AccessCodeResource::canCreate())
                ->form([
                    Forms\Components\Select::make('agenda_item_id')
                        ->label('Activiteit')
                        ->options(fn () => AccessCodeResource::agendaOpties())
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('aantal')
                        ->label('Hoeveel codes')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(2000)
                        ->default(50)
                        ->required(),

                    Forms\Components\TextInput::make('max_uses')
                        ->label('Maximaal aantal keer gebruiken')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(999)
                        ->default(1)
                        ->required()
                        ->helperText('Per code. Meestal 1: één code, één bezoeker.'),
                ])
                ->modalWidth('md')
                ->action(function (array $data): void {
                    $clubId = filament()->getTenant()?->id ?? auth()->user()?->club_id;

                    if (! $clubId) {
                        Notification::make()->danger()->title('Geen club geselecteerd')->send();

                        return;
                    }

                    $gemaakt = static::genereer(
                        $clubId,
                        (string) $data['agenda_item_id'],
                        (int) $data['aantal'],
                        (int) $data['max_uses'],
                    );

                    Notification::make()
                        ->title($gemaakt . ' codes aangemaakt')
                        ->body('Je kunt ze nu als QR afdrukken.')
                        ->success()
                        ->send();
                }),

            Actions\Action::make('import')
                ->label('Importeren')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->visible(fn (): bool => AccessCodeResource::canCreate())
                ->form([
                    Forms\Components\Select::make('agenda_item_id')
                        ->label('Activiteit')
                        ->options(fn () => AccessCodeResource::agendaOpties())
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('max_uses')
                        ->label('Maximaal aantal keer gebruiken')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(999)
                        ->default(1)
                        ->required(),

                    Forms\Components\FileUpload::make('file')
                        ->label('Excel bestand (.xlsx)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),

                    Forms\Components\Placeholder::make('format_hint')
                        ->label('Zo werkt het')
                        ->content('Eén kolom met de kop "code", daaronder de codes. Een tweede kolom "omschrijving" mag, maar hoeft niet. Codes die al bij deze activiteit staan worden overgeslagen.'),
                ])
                ->modalWidth('md')
                ->action(function (array $data): void {
                    $clubId = filament()->getTenant()?->id ?? auth()->user()?->club_id;

                    if (! $clubId) {
                        Notification::make()->danger()->title('Geen club geselecteerd')->send();

                        return;
                    }

                    $path   = Storage::disk('local')->path($data['file']);
                    $import = new AccessCodesImport(
                        $clubId,
                        (string) $data['agenda_item_id'],
                        (int) $data['max_uses'],
                    );

                    Excel::import($import, $path);

                    ImportNotifier::report(
                        $import->imported,
                        $import->created,
                        $import->skipped,
                        $import->errors,
                        'codes',
                    );

                    Storage::disk('local')->delete($data['file']);
                }),

            Actions\Action::make('afdrukvel')
                ->label('PDF-vel')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('agenda_item_id')
                        ->label('Activiteit')
                        ->options(fn () => AccessCodeResource::agendaOpties())
                        ->searchable()
                        ->required(),

                    Forms\Components\Toggle::make('alleen_ongebruikt')
                        ->label('Alleen codes die nog niet gebruikt zijn')
                        ->default(false),
                ])
                ->modalWidth('md')
                ->action(function (array $data): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $item = AgendaItem::find($data['agenda_item_id']);

                    $codes = AccessCode::query()
                        ->where('agenda_item_id', $data['agenda_item_id'])
                        ->where('is_active', true)
                        ->when(
                            (bool) ($data['alleen_ongebruikt'] ?? false),
                            fn ($q) => $q->whereColumn('used_count', '<', 'max_uses'),
                        )
                        ->orderBy('code')
                        ->get();

                    // De QR's vooraf tekenen en niet in de view: dan blijft de
                    // Blade een opmaakbestand en staat het zware werk hier.
                    $kaarten = $codes->map(fn (AccessCode $c): array => [
                        'code'  => $c->code,
                        'label' => $c->label,
                        'max'   => $c->max_uses,
                        'qr'    => Qr::pngDataUri($c->code, 260),
                    ])->all();

                    $pdf = Pdf::loadView('pdf.access-codes', [
                        'titel'     => $item?->title ?? 'Toegangscodes',
                        'datum'     => $item?->starts_at?->format('d-m-Y H:i') ?? '',
                        'clubNaam'  => filament()->getTenant()?->name,
                        'kaarten'   => $kaarten,
                    ])->setPaper('a4', 'portrait');

                    $bestandsnaam = 'toegangscodes-'
                        . \Illuminate\Support\Str::slug($item?->title ?? 'activiteit')
                        . '-' . now()->format('Y-m-d') . '.pdf';

                    return response()->streamDownload(
                        fn () => print ($pdf->output()),
                        $bestandsnaam,
                        ['Content-Type' => 'application/pdf'],
                    );
                }),

            Actions\CreateAction::make()->label('Code toevoegen'),
        ];
    }

    /**
     * Maakt codes aan tot het gevraagde aantal is bereikt.
     *
     * De botsingscontrole zit in de lus en niet in een vooraf gevulde lijst:
     * bij een tweede ronde genereren staan er al codes bij deze activiteit, en
     * die tellen net zo goed mee. Duizend pogingen per code is ruim - het
     * alfabet van 32 tekens over tien posities loopt niet snel vol.
     */
    private static function genereer(string $clubId, string $agendaItemId, int $aantal, int $maxUses): int
    {
        $bestaand = AccessCode::where('agenda_item_id', $agendaItemId)
            ->pluck('code')
            ->flip();

        $rijen  = [];
        $nu     = now();
        $maakte = 0;

        for ($i = 0; $i < $aantal; $i++) {
            $poging = 0;

            do {
                $code = AccessCode::nieuweCode();
                $poging++;
            } while ($bestaand->has($code) && $poging < 1000);

            if ($bestaand->has($code)) {
                break;
            }

            $bestaand[$code] = true;

            $rijen[] = [
                'id'             => (string) \Illuminate\Support\Str::uuid(),
                'club_id'        => $clubId,
                'agenda_item_id' => $agendaItemId,
                'code'           => $code,
                'label'          => null,
                'max_uses'       => $maxUses,
                'used_count'     => 0,
                'is_active'      => true,
                'created_at'     => $nu,
                'updated_at'     => $nu,
            ];
            $maakte++;
        }

        // In blokken invoegen: tweeduizend losse inserts is een minuut wachten
        // op een gedeelde host.
        foreach (array_chunk($rijen, 500) as $blok) {
            AccessCode::insert($blok);
        }

        return $maakte;
    }
}
