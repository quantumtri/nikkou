<?php

namespace App\Filament\Resources\SafetyKeywords\Pages;

use App\Filament\Resources\SafetyKeywords\SafetyKeywordResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSafetyKeyword extends CreateRecord
{
    protected static string $resource = SafetyKeywordResource::class;

    protected static ?string $title = 'Tambah kata kunci';

    protected static bool $canCreateAnother = false;

    protected function getRedirectUrl(): string
    {
        return SafetyKeywordResource::getUrl('index');
    }
}
