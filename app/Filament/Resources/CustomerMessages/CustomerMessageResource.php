<?php

namespace App\Filament\Resources\CustomerMessages;

use App\Filament\Resources\Concerns\AdminOnly;
use App\Filament\Resources\CustomerMessages\Pages\ListCustomerMessages;
use App\Models\ActivityLog;
use App\Models\ExperienceMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CustomerMessageResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = ExperienceMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?string $navigationLabel = 'Balasan pelanggan';

    protected static ?string $modelLabel = 'pesan';

    protected static ?string $pluralModelLabel = 'Balasan pelanggan';

    protected static ?string $slug = 'customer-messages';

    protected static ?int $navigationSort = 71;

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = ExperienceMessage::query()->where('is_serious', true)->whereNull('resolved_at')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Keluhan serius yang belum ditangani';
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['customer:id,name,whatsapp_number', 'loan.sales:id,name']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Waktu')->dateTime('j M H:i')->sortable(),
                TextColumn::make('customer.name')
                    ->label('Pelanggan')
                    ->searchable()
                    ->description(fn (ExperienceMessage $record) => '+'.$record->customer->whatsapp_number.($record->loan?->sales ? ' | Sales: '.str($record->loan->sales->name)->before(' ') : '')),
                TextColumn::make('direction')
                    ->label('Arah')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'in' ? 'Masuk' : 'Keluar')
                    ->color(fn (string $state) => $state === 'in' ? 'info' : 'gray'),
                TextColumn::make('body')->label('Pesan')->wrap()->limit(200)->searchable(),
                TextColumn::make('is_serious')
                    ->label('Keluhan')
                    ->badge()
                    ->state(fn (ExperienceMessage $record) => match (true) {
                        ! $record->is_serious => '',
                        $record->resolved_at !== null => 'Sudah ditangani',
                        default => 'Serius',
                    })
                    ->color(fn (ExperienceMessage $record) => $record->resolved_at === null ? 'danger' : 'gray'),
            ])
            ->filters([
                Filter::make('open_serious')->label('Keluhan serius belum ditangani')->query(fn (Builder $query) => $query->where('is_serious', true)->whereNull('resolved_at')),
                SelectFilter::make('direction')->label('Arah')->placeholder('Masuk dan keluar')->options(['in' => 'Masuk', 'out' => 'Keluar']),
            ])
            ->recordActions([
                Action::make('resolve')
                    ->label('Tandai ditangani')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (ExperienceMessage $record) => $record->is_serious && $record->resolved_at === null)
                    ->requiresConfirmation()
                    ->modalDescription('Tandai setelah pelanggan sudah dihubungi.')
                    ->action(function (ExperienceMessage $record) {
                        $record->forceFill(['resolved_at' => now(), 'resolved_by_id' => auth()->id()])->save();
                        ActivityLog::record($record, 'experience_message.resolved', auth()->user(), notes: mb_substr($record->body, 0, 120));
                    }),
            ])
            ->emptyStateHeading('Belum ada pesan')
            ->emptyStateDescription('Pesan follow-up yang dikirim dan balasan pelanggan muncul di sini.');
    }

    public static function getPages(): array
    {
        return ['index' => ListCustomerMessages::route('/')];
    }
}
