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

    protected function getRedirectUrl(): string
    {
        return LeadResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Lead $lead */
        $lead = $this->getRecord()->load('customer', 'accessories');
        $c = $lead->customer;

        return $data + [
            'name' => $c->name,
            'whatsapp_number' => $c->whatsapp_number,
            'city' => $c->city,
            'province' => $c->province,
            'gender' => $c->gender?->value,
            'age_range' => $c->age_range?->value,
            'path' => $lead->path->value,
            'accessories' => $lead->accessories->map(fn ($p) => ['product_id' => $p->id, 'quantity' => $p->pivot->quantity])->all(),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $service = app(LeadService::class);
        $data = $service->validate($data + ['path' => $record->path->value], withSales: true, creating: false);

        if ((int) $data['sales_id'] !== $record->sales_id) {
            $record->forceFill(['sales_id' => $data['sales_id']])->save();
        }

        return $service->update($record, $data, auth()->user());
    }
}
