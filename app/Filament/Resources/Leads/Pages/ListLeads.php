<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Widgets\LeadStatsOverview;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected ?string $subheading = 'Calon pelanggan dari semua sales.';

    protected function getHeaderWidgets(): array
    {
        return [LeadStatsOverview::class];
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah lead')];
    }
}
