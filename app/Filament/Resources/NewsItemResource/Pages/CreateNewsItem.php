<?php

declare(strict_types=1);

namespace App\Filament\Resources\NewsItemResource\Pages;

use App\Filament\Resources\NewsItemResource;
use App\Services\FcmService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateNewsItem extends CreateRecord
{
    protected static string $resource = NewsItemResource::class;

    /** Onthoudt de "Push-melding sturen"-keuze; send_push is geen DB-kolom. */
    protected bool $sendPush = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Lees + verwijder het niet-opgeslagen push-veld vóórdat het model wordt
        // aangemaakt (betrouwbaarder dan $this->data uitlezen in afterCreate).
        $this->sendPush = (bool) ($data['send_push'] ?? false);
        unset($data['send_push']);

        $tenant = filament()->getTenant();
        $user   = auth()->user();
        $data['club_id']   ??= $tenant?->id ?? $user?->club_id;
        $data['author_id'] ??= $user?->id;
        return $data;
    }

    /**
     * Stuur na het aanmaken een push naar alle app-gebruikers, als de "Push-
     * melding sturen"-toggle aanstond en het item gepubliceerd is. Faalt stil
     * (FcmService logt zelf); het aanmaken mag er nooit op stuklopen.
     */
    protected function afterCreate(): void
    {
        if (! $this->sendPush || ! $this->record->is_published) {
            return;
        }

        $ok = app(FcmService::class)->sendToTopic(
            'all_users',
            $this->record->title,
            Str::limit(trim(strip_tags((string) $this->record->body)), 140),
            ['initialPageName' => 'NewsPage', 'parameterData' => '{}'],
        );

        Notification::make()
            ->title($ok ? 'Push verstuurd naar alle gebruikers.' : 'Push niet verstuurd (controleer de FCM-configuratie).')
            ->{$ok ? 'success' : 'warning'}()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
