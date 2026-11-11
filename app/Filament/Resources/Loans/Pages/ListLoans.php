<?php

namespace App\Filament\Resources\Loans\Pages;

use App\Filament\Resources\Loans\LoanResource;
use App\Filament\Widgets\LoanStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListLoans extends ListRecords
{
    protected static string $resource = LoanResource::class;

    protected static ?string $title = 'Peminjaman dan posisi unit';

    public function getSubheading(): ?string
    {
        return now()->locale('id')->translatedFormat('l, j F Y').' | Admin: '.str(auth()->user()->name)->before(' ');
    }

    protected function getHeaderWidgets(): array
    {
        return [LoanStatsOverview::class];
    }
}
