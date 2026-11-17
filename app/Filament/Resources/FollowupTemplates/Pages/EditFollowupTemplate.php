<?php

namespace App\Filament\Resources\FollowupTemplates\Pages;

use App\Filament\Resources\FollowupTemplates\FollowupTemplateResource;
use App\Models\ActivityLog;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFollowupTemplate extends EditRecord
{
    protected static string $resource = FollowupTemplateResource::class;

    public function getTitle(): string
    {
        return 'Ubah teks: '.$this->getRecord()->title;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $changed = trim($data['body']) !== trim($record->body);
        $record->fill(['body' => trim($data['body'])]);

        // New wording has not been reviewed: it goes back to "not approved" and stops sending.
        if ($changed && $record->requires_review) {
            $record->fill(['is_active' => false, 'approved_at' => null, 'approved_by_id' => null]);
        }

        $record->save();

        if ($changed) {
            ActivityLog::record($record, 'followup_template.edited', auth()->user(), notes: $record->title);
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return FollowupTemplateResource::getUrl('index');
    }
}
