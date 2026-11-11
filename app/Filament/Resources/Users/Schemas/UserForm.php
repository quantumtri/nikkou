<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data akun')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->maxLength(255),
                    Select::make('role')
                        ->label('Peran')
                        ->options(UserRole::options())
                        ->required()
                        ->default(UserRole::Sales->value)
                        ->selectablePlaceholder(false)
                        ->helperText('Admin, Sales (aplikasi ponsel), atau Staf gudang (inventori dan pengembalian).'),
                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->disabledOn('edit')
                        ->helperText(fn (string $operation) => $operation === 'edit' ? 'Email dipakai untuk masuk dan tidak bisa diubah.' : null)
                        ->validationMessages(['unique' => 'Email ini sudah dipakai akun lain.']),
                    TextInput::make('whatsapp_number')
                        ->label('Nomor WhatsApp')
                        ->tel()
                        ->placeholder('08xx xxxx xxxx')
                        ->maxLength(20)
                        ->regex('/^(\+?62|0)\s*8[\d\s\-]{7,14}$/')
                        ->validationMessages(['regex' => 'Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.'])
                        ->dehydrateStateUsing(fn (?string $state) => filled($state) ? preg_replace('/[\s\-]/', '', $state) : null),
                ]),
        ]);
    }
}
