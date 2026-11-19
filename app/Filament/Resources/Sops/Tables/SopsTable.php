<?php

namespace App\Filament\Resources\Sops\Tables;

use App\Models\Sop;
use App\Models\User;
use App\Services\SopService;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SopsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->recordUrl(fn (Sop $record): string => route('filament.admin.resources.sops.view', $record))
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('title')->label('Judul')->searchable()->wrap(),
                TextColumn::make('owner.name')->label('Pemilik')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state) => Sop::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        Sop::ACTIVE => 'success',
                        Sop::ARCHIVED => 'gray',
                        default => 'warning',
                    }),
                TextColumn::make('activeVersion.number')->label('Versi aktif')->prefix('v')->placeholder('-'),
                TextColumn::make('activeVersion.effective_from')->label('Berlaku sejak')->date('j M Y')->placeholder('-'),
                TextColumn::make('review_on')->label('Tinjauan berikutnya')->date('j M Y')->placeholder('-')
                    ->description(fn (Sop $r) => $r->review_on && $r->review_on->lte(today()->addDays(30))
                        ? ($r->review_on->isPast() ? 'Terlambat ditinjau' : 'Segera ditinjau') : null)
                    ->color(fn (Sop $r) => $r->review_on && $r->review_on->lte(today()->addDays(30)) ? 'danger' : null),
                TextColumn::make('forms')->label('Formulir terkait')
                    ->state(fn (Sop $r) => $r->activeVersion?->forms->count() ?? 0)->alignCenter(),
                TextColumn::make('readers')->label('Sudah baca')
                    ->state(function (Sop $r) {
                        if (! $r->activeVersion) {
                            return '-';
                        }
                        $assigned = app(SopService::class)->assignedUsers($r);

                        return $r->activeVersion->acknowledgements->whereIn('user_id', $assigned->pluck('id'))->count().' / '.$assigned->count();
                    })->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options(Sop::STATUS_LABELS),
                SelectFilter::make('owner_id')->label('Pemilik')
                    ->options(fn () => User::where('role', 'admin')->orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                ViewAction::make()->label('Buka'),
            ]);
    }
}
