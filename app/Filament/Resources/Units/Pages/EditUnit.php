<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use App\Models\ActivityLog;
use Filament\Resources\Pages\EditRecord;

class EditUnit extends EditRecord
{
    protected static string $resource = UnitResource::class;

    public function getTitle(): string
    {
        return 'Ubah data unit '.$this->getRecord()->code;
    }

    protected function afterSave(): void
    {
        ActivityLog::record($this->getRecord(), 'unit.updated', auth()->user(), notes: 'Data unit diubah.');
    }

    protected function getRedirectUrl(): string
    {
        return UnitResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
