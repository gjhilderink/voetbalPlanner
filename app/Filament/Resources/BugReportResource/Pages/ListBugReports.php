<?php

declare(strict_types=1);

namespace App\Filament\Resources\BugReportResource\Pages;

use App\Filament\Resources\BugReportResource;
use Filament\Resources\Pages\ListRecords;

class ListBugReports extends ListRecords
{
    protected static string $resource = BugReportResource::class;
}
