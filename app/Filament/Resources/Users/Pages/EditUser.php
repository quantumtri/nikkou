<?php

namespace App\Filament\Resources\Users\Pages;

use App\Exceptions\AccountRuleViolation;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return 'Ubah akun '.$this->getRecord()->name;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return app(UserService::class)->update($record, $data, auth()->user());
        } catch (AccountRuleViolation $e) {
            throw ValidationException::withMessages(['data.role' => $e->getMessage()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()->success()->title('Akun disimpan.');
    }
}
