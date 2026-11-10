<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use App\Models\Unit;
use App\Services\LabelService;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnit extends ViewRecord
{
    protected static string $resource = UnitResource::class;

    protected string $view = 'filament.units.view';

    public function getTitle(): string
    {
        return 'Unit '.$this->getRecord()->code;
    }

    public function getSubheading(): ?string
    {
        /** @var Unit $unit */
        $unit = $this->getRecord();

        return "{$unit->type->name} | Masuk {$unit->received_on->locale('id')->translatedFormat('j M Y')} | {$unit->location}";
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->label('Ubah data')->outlined(),
        ];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        /** @var Unit $unit */
        $unit = $this->getRecord()->load([
            'createdBy:id,name',
            'components',
            'documents',
            'photos' => fn ($query) => $query->where('stage', 'initial'),
            'loans' => fn ($query) => $query->with(['customer:id,name', 'sales:id,name', 'lead'])->latest('starts_on'),
            'activityLogs' => fn ($query) => $query->with('user:id,name')->latest('created_at')->latest('id'),
        ]);

        return [
            'unit' => $unit,
            'canSeeDocuments' => auth()->user()->can('update', $unit),
            'label' => auth()->user()->can('printLabel', Unit::class) ? app(LabelService::class)->labelData($unit) : null,
        ];
    }
}
