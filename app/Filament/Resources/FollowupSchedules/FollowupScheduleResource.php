<?php

namespace App\Filament\Resources\FollowupSchedules;

use App\Filament\Resources\Concerns\AdminOnly;
use App\Filament\Resources\FollowupSchedules\Pages\ListFollowupSchedules;
use App\Models\FollowupSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class FollowupScheduleResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = FollowupSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?string $navigationLabel = 'Jadwal kirim';

    protected static ?string $modelLabel = 'jadwal';

    protected static ?string $pluralModelLabel = 'Jadwal kirim';

    protected static ?string $slug = 'followup-schedules';

    protected static ?int $navigationSort = 72;

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer:id,name,whatsapp_number', 'loan.unit:id,code', 'template:id,title']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('send_at', 'desc')
            ->columns([
                TextColumn::make('customer.name')->label('Pelanggan')->searchable()->description(fn (FollowupSchedule $record) => $record->loan->unit->code),
                TextColumn::make('template.title')->label('Langkah')->wrap(),
                TextColumn::make('send_at')->label('Kirim pada')->dateTime('j M Y H:i')->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => FollowupSchedule::STATUS_LABELS[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        FollowupSchedule::SENT => 'info',
                        FollowupSchedule::FAILED => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('error')->label('Catatan')->placeholder('-')->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->placeholder('Semua status')->options(FollowupSchedule::STATUS_LABELS),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (FollowupSchedule $record) => $record->status === FollowupSchedule::PENDING)
                    ->requiresConfirmation()
                    ->action(fn (FollowupSchedule $record) => $record->update(['status' => FollowupSchedule::CANCELLED])),
            ])
            ->emptyStateHeading('Belum ada jadwal')
            ->emptyStateDescription('Jadwal dibuat otomatis saat peminjaman home trial disimpan, untuk pelanggan yang setuju dihubungi lewat WhatsApp dan hanya untuk teks yang sudah aktif.');
    }

    public static function getPages(): array
    {
        return ['index' => ListFollowupSchedules::route('/')];
    }
}
