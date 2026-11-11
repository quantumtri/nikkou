<?php

namespace App\Filament\Pages;

use App\Enums\UserRole;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\LeadFunnel;
use App\Filament\Widgets\SalesTable;
use App\Filament\Widgets\UnitStatusChart;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dasbor';

    protected ?string $subheading = 'Ringkasan leads, peminjaman, dan unit.';

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    Select::make('period')
                        ->label('Periode')
                        ->options([7 => '7 hari terakhir', 30 => '30 hari terakhir', 90 => '90 hari terakhir', 0 => 'Semua waktu'])
                        ->default(30)
                        ->selectablePlaceholder(false),
                    Select::make('sales_id')
                        ->label('Sales')
                        ->placeholder('Semua sales')
                        ->options(fn () => User::query()->where('role', UserRole::Sales)->orderBy('name')->pluck('name', 'id')),
                ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [DashboardStats::class, UnitStatusChart::class, LeadFunnel::class, SalesTable::class];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
