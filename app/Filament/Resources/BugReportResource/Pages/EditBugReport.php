<?php

declare(strict_types=1);

namespace App\Filament\Resources\BugReportResource\Pages;

use App\Filament\Resources\BugReportResource;
use Filament\Resources\Pages\EditRecord;

class EditBugReport extends EditRecord
{
    protected static string $resource = BugReportResource::class;
}
