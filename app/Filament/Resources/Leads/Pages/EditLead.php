<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use App\Services\LeadService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLead extends EditRecord
{
    protected static string $resource = LeadResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Lead $lead */
        $lead = $this->getRecord()->load('customer');
        $c = $lead->customer;

        return $data + [
            'name' => $c->name,
            'whatsapp_number' => $c->whatsapp_number,
            'city' => $c->city,
            'province' => $c->province,
            'gender' => $c->gender?->value,
            'age_range' => $c->age_range?->value,
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = app(LeadService::class);
        $data = $service->validate($data, withSales: true);

        if ((int) $data['sales_id'] !== $record->sales_id) {
            $record->forceFill(['sales_id' => $data['sales_id']])->save();
        }

        return $service->update($record, $data, auth()->user());
    }
}
