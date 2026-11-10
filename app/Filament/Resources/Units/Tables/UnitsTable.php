<?php

namespace App\Filament\Resources\Units\Tables;

use App\Enums\UnitStatus;
use App\Filament\Resources\Units\UnitResource;
use App\Models\Unit;
use App\Models\UnitType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UnitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->searchPlaceholder('Cari kode unit')
            ->recordUrl(fn (Unit $record) => UnitResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('code')
                    ->label('Kode unit')
                    ->searchable()
                    ->sortable()
                    ->color('primary'),
                TextColumn::make('type.name')
                    ->label('Tipe')
                    ->sortable(),
                TextColumn::make('received_on')
                    ->label('Tanggal masuk')
                    ->date('j M Y')
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Posisi sekarang')
                    ->state(fn (Unit $record) => $record->currentPosition()),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Unit $record) => $record->statusLabel())
                    ->color(fn (Unit $record) => self::statusColor($record)),
                TextColumn::make('label_printed_at')
                    ->label('Label')
                    ->badge()
                    ->state(fn (Unit $record) => $record->label_printed_at ? 'Sudah dicetak' : 'Belum dicetak')
                    ->color(fn (Unit $record) => $record->label_printed_at ? 'gray' : 'danger'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('Semua status')
                    ->options(UnitStatus::options()),
                SelectFilter::make('unit_type_id')
                    ->label('Tipe')
                    ->placeholder('Semua tipe')
                    ->options(fn () => UnitType::query()->orderBy('name')->pluck('name', 'id')),
                SelectFilter::make('location')
                    ->label('Lokasi')
                    ->placeholder('Semua lokasi')
                    ->options(fn () => Unit::query()->distinct()->orderBy('location')->pluck('location', 'location')),
            ])
            ->emptyStateHeading('Belum ada unit')
            ->emptyStateDescription('Tambah unit pertama untuk mulai mencatat inventori.');
    }

    public static function statusColor(Unit $unit): string
    {
        return match (true) {
            $unit->status === UnitStatus::Available => 'info',
            $unit->status === UnitStatus::OnLoan => 'danger',
            default => 'gray',
        };
    }
}
