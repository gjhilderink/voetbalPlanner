<?php

declare(strict_types=1);

namespace App\Filament\Resources\DocumentationResource\Pages;

use App\Filament\Resources\DocumentationResource;
use App\Models\Documentation;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentations extends ListRecords
{
    protected static string $resource = DocumentationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn() => DocumentationResource::canCreate()),

            Action::make('exportPdf')
                ->label('PDF exporteren')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (): \Symfony\Component\HttpFoundation\StreamedResponse {
                    $sections = Documentation::query()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get()
                        ->groupBy('category');

                    $pdf = Pdf::loadView('pdf.documentation', [
                        'sections'       => $sections,
                        'categoryLabels' => Documentation::$categoryLabels,
                        'generatedAt'    => now()->format('d-m-Y H:i'),
                    ])->setPaper('a4', 'portrait');

                    return response()->streamDownload(
                        fn() => print($pdf->output()),
                        'voetbalplanner-documentatie-' . now()->format('Y-m-d') . '.pdf',
                        ['Content-Type' => 'application/pdf'],
                    );
                }),
        ];
    }
}
