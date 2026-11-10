<?php

namespace App\Filament\Resources\Units\Pages;

use App\Filament\Resources\Units\UnitResource;
use App\Services\UnitService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUnit extends CreateRecord
{
    protected static string $resource = UnitResource::class;

    protected static ?string $title = 'Tambah unit inventori';

    protected static bool $canCreateAnother = false;

    private const DOCUMENT_FIELDS = [
        'doc_packing_list' => 'packing_list',
        'doc_invoice' => 'invoice',
        'doc_bl_awb' => 'bl_awb',
        'doc_pib' => 'pib',
        'doc_other' => 'other',
    ];

    public function getSubheading(): ?string
    {
        return 'Satu form per unit. Penginput tercatat otomatis: '.auth()->user()->name.'.';
    }

    protected function handleRecordCreation(array $data): Model
    {
        $documents = [];

        foreach (self::DOCUMENT_FIELDS as $field => $type) {
            $names = Arr::wrap($data[$field.'_names'] ?? []);

            foreach (Arr::wrap($data[$field] ?? []) as $path) {
                $documents[] = [
                    'type' => $type,
                    'file_path' => $path,
                    'original_name' => $names[$path] ?? (count($names) === 1 ? reset($names) : null),
                ];
            }
        }

        return app(UnitService::class)->create([
            ...Arr::only($data, ['code', 'unit_type_id', 'received_on', 'forwarder', 'location', 'initial_notes']),
            'components' => array_values($data['components'] ?? []),
            'documents' => $documents,
            'photos' => array_values(Arr::wrap($data['photos'] ?? [])),
        ], auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return UnitResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Unit disimpan. Status awal: Tersedia.';
    }
}
