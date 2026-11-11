<?php

namespace App\Filament\Resources\Loans\Tables;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\UserRole;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Loan;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoansTable
{
    private const OVERDUE = 'overdue';

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_on')
            ->defaultKeySort(false)
            ->searchPlaceholder('Cari kode unit atau nama')
            ->columns([
                TextColumn::make('unit.code')
                    ->label('Unit')
                    ->color('primary')
                    ->url(fn (Loan $record) => UnitResource::getUrl('view', ['record' => $record->unit_id]))
                    ->searchable(),
                TextColumn::make('borrower')
                    ->label('Pelanggan / event')
                    ->state(fn (Loan $record) => $record->borrowerName())
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->whereHas('customer', fn (Builder $c) => $c->where('name', 'ilike', "%{$search}%"))
                        ->orWhere('event_name', 'ilike', "%{$search}%"))),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->formatStateUsing(fn (LoanType $state) => $state->label()),
                TextColumn::make('sales.name')
                    ->label('Sales')
                    ->formatStateUsing(fn (string $state) => str($state)->before(' ')->toString()),
                TextColumn::make('starts_on')
                    ->label('Pinjam')
                    ->date('j M')
                    ->sortable(),
                TextColumn::make('due_on')
                    ->label('Jatuh tempo')
                    ->date('j M')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Loan $record) => self::statusText($record))
                    ->color(fn (Loan $record) => $record->isActive() ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('sales_id')
                    ->label('Sales')
                    ->placeholder('Semua sales')
                    ->options(fn () => User::query()->where('role', UserRole::Sales)->orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->placeholder('Home trial dan event')
                    ->options(LoanType::options()),
                SelectFilter::make('event_name')
                    ->label('Event')
                    ->placeholder('Semua event')
                    ->options(fn () => Loan::query()->whereNotNull('event_name')->distinct()->orderBy('event_name')->pluck('event_name', 'event_name')),
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('Semua status')
                    ->options([
                        LoanStatus::Active->value => 'Belum kembali',
                        self::OVERDUE => 'Lewat jatuh tempo',
                        LoanStatus::Returned->value => 'Sudah kembali',
                    ])
                    ->default(LoanStatus::Active->value)
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        self::OVERDUE => $query->where('status', LoanStatus::Active)->whereDate('due_on', '<', today()),
                        null, '' => $query,
                        default => $query->where('status', $data['value']),
                    }),
            ])
            ->emptyStateHeading('Tidak ada peminjaman')
            ->emptyStateDescription('Peminjaman dicatat oleh sales lewat aplikasi di ponsel.');
    }

    public static function statusText(Loan $loan): string
    {
        if ($loan->isOverdue()) {
            $days = (int) $loan->due_on->diffInDays(today());

            return "Lewat {$days} hari";
        }

        if (! $loan->isActive()) {
            return 'Kembali '.($loan->returned_at?->locale('id')->translatedFormat('j M') ?? '');
        }

        return $loan->status->label();
    }
}
