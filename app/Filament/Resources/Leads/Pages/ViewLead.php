<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Leads\Schemas\SymptomFormSchema;
use App\Models\Lead;
use App\Services\SymptomFormService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

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
        return [
            Action::make('symptomForm')
                ->label(fn (Lead $record) => $record->symptomForm ? 'Ubah form gejala' : 'Isi form gejala')
                ->icon('heroicon-o-clipboard-document-list')->color('gray')
                ->slideOver()->modalWidth(Width::FourExtraLarge)->modalHeading('Form gejala awal')->modalSubmitActionLabel('Simpan form')
                ->fillForm(fn (Lead $record) => SymptomFormSchema::defaults($record))
                ->schema(SymptomFormSchema::components())
                ->action(function (array $data, Lead $record, Action $action) {
                    $service = app(SymptomFormService::class);
                    try {
                        $service->save($record, $service->validate($data, $record), auth()->user());
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                        $action->halt();
                    }
                    $this->record->refresh();
                    Notification::make()->success()->title('Form gejala disimpan')->send();
                }),
            EditAction::make()->label('Ubah'),
        ];
    }
}
