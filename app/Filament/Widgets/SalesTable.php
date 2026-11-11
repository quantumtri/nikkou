<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/** Per-sales performance (spec A1). Everything is computed from loans.sales_id and leads.sales_id. */
class SalesTable extends TableWidget
{
    use UsesDashboardFilters;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Per sales';

    public function table(Table $table): Table
    {
        $since = $this->periodStart();
        $sales = $this->salesFilter();

        return $table
            ->query(
                User::query()
                    ->where('role', UserRole::Sales)
                    ->when($sales, fn (Builder $q) => $q->whereKey($sales))
                    ->withCount([
                        'leads as leads_total' => fn (Builder $q) => $q->when($since, fn ($q) => $q->where('created_at', '>=', $since)),
                        'leads as leads_purchased' => fn (Builder $q) => $q->where('status', LeadStatus::Purchased)->when($since, fn ($q) => $q->where('created_at', '>=', $since)),
                        'loans as loans_total' => fn (Builder $q) => $q->when($since, fn ($q) => $q->where('starts_on', '>=', $since)),
                        'loans as loans_held' => fn (Builder $q) => $q->where('status', LoanStatus::Active),
                    ])
            )
            ->paginated(false)
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Sales')->formatStateUsing(fn (string $state) => str($state)->before(' ')->toString()),
                TextColumn::make('leads_total')->label('Leads')->numeric(),
                TextColumn::make('loans_total')->label('Peminjaman')->numeric(),
                TextColumn::make('leads_purchased')->label('Penjualan')->numeric()->formatStateUsing(fn ($state) => "{$state} unit"),
                TextColumn::make('conversion')
                    ->label('Konversi')
                    ->state(fn (User $record) => $record->leads_total > 0 ? round($record->leads_purchased / $record->leads_total * 100).'%' : '-'),
                TextColumn::make('loans_held')->label('Unit dipegang')->numeric(),
            ])
            ->emptyStateHeading('Belum ada sales');
    }
}
