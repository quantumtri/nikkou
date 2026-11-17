<?php

namespace App\Filament\Resources\FollowupSchedules\Pages;

use App\Filament\Resources\FollowupSchedules\FollowupScheduleResource;
use Filament\Resources\Pages\ListRecords;

class ListFollowupSchedules extends ListRecords
{
    protected static string $resource = FollowupScheduleResource::class;

    protected static ?string $title = 'Jadwal kirim';
}
