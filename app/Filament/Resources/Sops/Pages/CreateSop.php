<?php

namespace App\Filament\Resources\Sops\Pages;

use App\Filament\Resources\Sops\SopResource;
use App\Models\Sop;
use App\Models\SopAssignment;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateSop extends CreateRecord
{
    protected static string $resource = SopResource::class;

    protected static bool $canCreateAnother = false;

    protected ?string $subheading = 'SOP baru dibuat sebagai draf kosong. Isi langkahnya di editor.';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(20)->placeholder('SOP-11')
                ->unique('sops', 'code')
                ->dehydrateStateUsing(fn (string $state) => mb_strtoupper(trim($state))),
            TextInput::make('title')->label('Judul')->required()->maxLength(255),
            Select::make('owner_id')->label('Pemilik')->required()
                ->options(fn () => User::where('role', 'admin')->where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                ->default(fn () => auth()->id()),
            CheckboxList::make('roles')->label('Wajib dibaca oleh')
                ->options(['admin' => 'Admin', 'warehouse' => 'Gudang', 'sales' => 'Sales'])->columns(3),
        ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $roles = $data['roles'] ?? [];
        unset($data['roles']);

        $sop = Sop::create($data);
        foreach ($roles as $role) {
            SopAssignment::create(['sop_id' => $sop->id, 'role' => $role]);
        }

        return $sop;
    }

    protected function getRedirectUrl(): string
    {
        return SopResource::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
