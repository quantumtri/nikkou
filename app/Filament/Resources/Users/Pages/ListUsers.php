<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\AccessMatrix;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Akun dan akses';

    protected ?string $subheading = 'Sales hanya melihat data peminjaman miliknya sendiri.';

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah akun')];
    }

    protected function getFooterWidgets(): array
    {
        return [AccessMatrix::class];
    }
}
