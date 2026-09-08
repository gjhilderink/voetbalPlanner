<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\AccessCodeResource;
use App\Filament\Resources\OrderResource;
use App\Models\AccessCode;
use App\Models\AgendaItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Alle verkochte kaarten van één activiteit in één bestand, om
            // achter de balie uit te printen. Het aanvinken in de tabel doet
            // hetzelfde voor een paar bestellingen; dit is de hele stapel.
            Actions\Action::make('afdrukvel')
                ->label('Kaarten afdrukken')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->form([
                    Forms\Components\Select::make('agenda_item_id')
                        ->label('Activiteit')
                        ->options(fn () => AccessCodeResource::agendaOpties())
                        ->searchable()
                        ->required(),

                    Forms\Components\Toggle::make('alleen_ongescand')
                        ->label('Alleen kaarten die nog niet gescand zijn')
                        ->default(false),
                ])
                ->modalWidth('md')
                ->modalSubmitActionLabel('Pdf maken')
                ->action(function (array $data): ?StreamedResponse {
                    $item = AgendaItem::find($data['agenda_item_id']);

                    $codes = AccessCode::query()
                        ->where('agenda_item_id', $data['agenda_item_id'])
                        // Alleen verkochte kaarten: codes die de club zelf heeft
                        // aangemaakt staan op het bestaande PDF-vel met codes.
                        ->whereNotNull('order_id')
                        ->where('is_active', true)
                        ->when(
                            (bool) ($data['alleen_ongescand'] ?? false),
                            fn ($q) => $q->whereColumn('used_count', '<', 'max_uses'),
                        )
                        ->with(['order', 'club', 'agendaItem'])
                        ->get()
                        // Op koper en dan op code: de kaarten van dezelfde
                        // bestelling komen zo achter elkaar uit de printer.
                        ->sortBy(fn (AccessCode $code): string => ($code->order?->buyer_name ?? '') . '|' . $code->code)
                        ->values();

                    return OrderResource::kaartenDownload(
                        $codes,
                        'kaarten-' . Str::slug($item?->title ?? 'activiteit')
                            . '-' . now()->format('Y-m-d') . '.pdf',
                    );
                }),
        ];
    }
}
