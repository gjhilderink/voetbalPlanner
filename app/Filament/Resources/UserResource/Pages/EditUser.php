<?php

declare(strict_types=1);

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Mail\WelcomeUserMail;
use App\Models\User;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->welkomstmailAction(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Stuurt een welkomstmail met de inlognaam en een link om zelf een
     * wachtwoord te kiezen.
     *
     * Geen wachtwoord in de mail: dat zou per post rondgaan en daarna in de
     * inbox blijven staan. In plaats daarvan een gewone herstel-token, zodat de
     * ontvanger er zelf een kiest en niemand anders hem kent.
     */
    private function welkomstmailAction(): Actions\Action
    {
        return Actions\Action::make('welkomstmail')
            ->label('Welkomstmail versturen')
            ->icon('heroicon-o-envelope')
            ->color('info')
            ->requiresConfirmation()
            ->modalHeading('Welkomstmail versturen')
            // $this->record en geen geïnjecteerde $record: op een bewerkpagina is
            // dat altijd het geopende account, ongeacht hoe de actie wordt gemount.
            ->modalDescription(fn (): string => sprintf(
                'Stuurt een mail naar %s met de inlognaam en een link om zelf een wachtwoord in te stellen. '
                . 'De link is %d minuten geldig; daarna kan de ontvanger op de inlogpagina een nieuwe aanvragen.',
                $this->record->email,
                (int) config('auth.passwords.users.expire', 60),
            ))
            ->modalSubmitActionLabel('Versturen')
            ->action(function (): void {
                /** @var User $record */
                $record = $this->record;

                if (! $record->is_active) {
                    Notification::make()
                        ->warning()
                        ->title('Account staat op inactief')
                        ->body('Zet het account eerst op actief; met een inactief account kan er niet worden ingelogd.')
                        ->send();

                    return;
                }

                try {
                    // Een gewone wachtwoordherstel-token: dezelfde die de
                    // "wachtwoord vergeten"-link gebruikt, dus de instelpagina
                    // van Filament accepteert hem zonder extra werk.
                    $token = Password::broker()->createToken($record);
                    $url   = filament()->getResetPasswordUrl($token, $record);

                    Mail::to($record->email)->send(new WelcomeUserMail(
                        $record,
                        $url,
                        (int) config('auth.passwords.users.expire', 60),
                    ));
                } catch (\Throwable $e) {
                    report($e);

                    Notification::make()
                        ->danger()
                        ->title('Versturen mislukt')
                        ->body($e->getMessage())
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title('Welkomstmail verstuurd')
                    ->body('Verstuurd naar ' . $record->email . '.')
                    ->send();
            });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['managed_team_functions'] = $this->record->managedTeams()
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'team_id' => $t->id,
                'role'    => $t->pivot->role ?: 'coach',
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function afterSave(): void
    {
        if (! array_key_exists('managed_team_functions', $this->data)) {
            return;
        }
        UserResource::syncManagedTeamFunctions($this->record, $this->data['managed_team_functions'] ?? []);
    }
}
