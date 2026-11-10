<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use App\Filament\Widgets\UnitStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUnits extends ListRecords
{
    protected static string $resource = UnitResource::class;

    protected ?string $subheading = 'Semua unit beserta status dan posisi terakhir. Klik unit untuk melihat detail dan riwayat.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah unit'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [UnitStatsOverview::class];
    }
}
