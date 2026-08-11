<?php

declare(strict_types=1);

namespace App\Filament\Resources\MatchGuestInvitationResource\Pages;

use App\Filament\Resources\MatchGuestInvitationResource;
use Filament\Resources\Pages\ListRecords;

class ListMatchGuestInvitations extends ListRecords
{
    protected static string $resource = MatchGuestInvitationResource::class;
}
