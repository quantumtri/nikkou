<?php

namespace App\Filament\Resources\SafetyKeywords\Pages;

use App\Filament\Resources\SafetyKeywords\SafetyKeywordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSafetyKeywords extends ListRecords
{
    protected static string $resource = SafetyKeywordResource::class;

    protected static ?string $title = 'Kata kunci keluhan serius';

    protected ?string $subheading = 'Balasan pelanggan yang memuat kata ini ditandai sebagai keluhan serius dan langsung diberitahukan ke admin dan sales. Daftar awal adalah draf: tinjau dengan tenaga kesehatan.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah kata kunci')];
    }
}
