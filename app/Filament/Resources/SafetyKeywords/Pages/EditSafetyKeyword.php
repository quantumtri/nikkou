<?php

namespace App\Filament\Resources\SafetyKeywords\Pages;

use App\Filament\Resources\SafetyKeywords\SafetyKeywordResource;
use Filament\Resources\Pages\EditRecord;

class EditSafetyKeyword extends EditRecord
{
    protected static string $resource = SafetyKeywordResource::class;

    protected static ?string $title = 'Ubah kata kunci';

    protected function getRedirectUrl(): string
    {
        return SafetyKeywordResource::getUrl('index');
    }
}
