<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Tables\UsersTable;
use App\Filament\Resources\Users\UserResource;
use App\Services\UserService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Tambah akun';

    protected static bool $canCreateAnother = false;

    protected function handleRecordCreation(array $data): Model
    {
        return app(UserService::class)->invite($data, auth()->user());
    }

    protected function afterCreate(): void
    {
        $user = $this->getRecord();

        UsersTable::announceLink($user, app(UserService::class)->linkFor($user));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Akun dibuat.';
    }

    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index');
    }
}
