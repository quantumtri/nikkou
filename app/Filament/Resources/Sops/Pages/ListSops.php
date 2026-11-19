<?php

namespace App\Filament\Resources\Sops\Pages;

use App\Filament\Pages\SopDiagrams;
use App\Filament\Resources\Sops\SopResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSops extends ListRecords
{
    protected static string $resource = SopResource::class;

    protected ?string $subheading = 'Prosedur kerja Nikkou. Setiap perubahan menjadi versi baru yang perlu disetujui pemilik.';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('diagrams')->label('Lihat semua diagram')->outlined()->url(fn () => SopDiagrams::getUrl()),
            CreateAction::make()->label('SOP baru'),
        ];
    }
}
