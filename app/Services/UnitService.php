<?php

namespace App\Services;

use App\Enums\UnitDocumentType;
use App\Enums\UnitPhotoStage;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnitService
{
    /**
     * Register a new unit. The creator is always the acting user and the
     * initial status is always "available" (spec A5).
     *
     * @param  array{
     *     code: string, unit_type_id: int, received_on: string, forwarder?: ?string, location?: ?string,
     *     initial_notes?: ?string,
     *     components?: array<int, array{name: string, is_present: bool}>,
     *     documents?: array<int, array{type: string, file_path: string, original_name?: ?string}>,
     *     photos?: array<int, string>,
     * }  $data
     */
    public function create(array $data, User $actor): Unit
    {
        return DB::transaction(function () use ($data, $actor) {
            $unit = Unit::create([
                'code' => strtoupper(trim($data['code'])),
                'unit_type_id' => $data['unit_type_id'],
                'received_on' => $data['received_on'],
                'created_by_id' => $actor->id,
                'forwarder' => $data['forwarder'] ?? null,
                'location' => ($data['location'] ?? null) ?: 'Gudang Surabaya',
                'initial_notes' => $data['initial_notes'] ?? null,
            ]);

            foreach ($data['components'] ?? [] as $component) {
                $unit->components()->create([
                    'name' => $component['name'],
                    'is_present' => (bool) $component['is_present'],
                ]);
            }

            foreach ($data['documents'] ?? [] as $document) {
                $unit->documents()->create([
                    'type' => UnitDocumentType::from($document['type']),
                    'file_path' => $document['file_path'],
                    'original_name' => $document['original_name'] ?? null,
                ]);
            }

            foreach ($data['photos'] ?? [] as $path) {
                $unit->photos()->create(['stage' => UnitPhotoStage::Initial, 'file_path' => $path]);
            }

            ActivityLog::record($unit, 'unit.created', $actor, null, $unit->status->value, 'Masuk gudang.');

            return $unit;
        });
    }

    /** Suggest the next code after the highest existing NK-#### code. */
    public function suggestNextCode(): string
    {
        $max = Unit::query()
            ->where('code', 'like', 'NK-%')
            ->pluck('code')
            ->map(fn (string $code) => (int) preg_replace('/\D/', '', $code))
            ->max() ?? 0;

        return sprintf('NK-%04d', $max + 1);
    }
}
