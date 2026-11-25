<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLead extends ViewRecord
{
    protected static string $resource = LeadResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->customer->name;
    }

    public function getSubheading(): ?string
    {
        $lead = $this->getRecord();

        return $lead->code.' | '.$lead->path->label().' | '.$lead->status->label();
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()->label('Ubah')];
    }
}
