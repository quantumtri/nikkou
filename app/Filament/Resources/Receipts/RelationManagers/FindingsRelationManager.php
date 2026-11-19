<?php

namespace App\Filament\Resources\Receipts\RelationManagers;

use App\Exceptions\ReceiptRuleViolation;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFinding;
use App\Models\User;
use App\Services\ReceiptService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FindingsRelationManager extends RelationManager
{
    protected static string $relationship = 'findings';

    protected static ?string $title = 'Temuan';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Temuan';
    }

    /** @return array<int, Component> */
    public static function formSchema(?GoodsReceipt $receipt = null): array
    {
        return [
            Select::make('type')->label('Jenis')->options(collect(GoodsReceiptFinding::TYPES)->map(fn ($t) => $t[0])->all())->required()->live()
                ->afterStateUpdated(fn ($set, $state) => $set('due_on', $state ? today()->addWeekdays(GoodsReceiptFinding::TYPES[$state][1])->toDateString() : null)),
            Select::make('goods_receipt_item_id')->label('Unit terkait (bila ada)')->options(fn () => $receipt?->items->mapWithKeys(fn ($i) => [$i->id => $i->unit_code.($i->serial_number ? " | SN {$i->serial_number}" : '')])->all() ?? [])->placeholder('Seluruh kiriman'),
            Textarea::make('description')->label('Deskripsi')->required()->rows(3)->maxLength(2000)->columnSpanFull(),
            Select::make('action')->label('Tindakan')->options(GoodsReceiptFinding::ACTIONS),
            DatePicker::make('due_on')->label('Batas waktu')->native(false)->displayFormat('d/m/Y')->helperText('Otomatis dari jenis temuan: hari yang sama sampai 2 hari kerja.'),
            Select::make('responsible_id')->label('Penanggung jawab')->options(fn () => User::query()->where('is_active', true)->whereIn('role', ['admin', 'warehouse'])->orderBy('name')->pluck('name', 'id')),
            FileUpload::make('photos')->label('Foto temuan')->image()->multiple()->maxFiles(6)->maxSize(10240)->disk('local')->directory('receipts/findings')->visibility('private')->columnSpanFull(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('type')
            ->columns([
                TextColumn::make('type')->label('Jenis')->formatStateUsing(fn (string $state) => GoodsReceiptFinding::typeLabel($state))->description(fn (GoodsReceiptFinding $record) => $record->item?->unit_code),
                TextColumn::make('description')->label('Deskripsi')->wrap()->limit(160),
                TextColumn::make('action')->label('Tindakan')->formatStateUsing(fn (?string $state) => $state ? (GoodsReceiptFinding::ACTIONS[$state] ?? '-') : '-'),
                TextColumn::make('due_on')->label('Batas waktu')->date('j M')
                    ->color(fn (GoodsReceiptFinding $record) => $record->isOverdue() ? 'danger' : null)
                    ->description(fn (GoodsReceiptFinding $record) => $record->isOverdue() ? 'Lewat batas waktu' : null),
                TextColumn::make('responsible.name')->label('Penanggung jawab')->placeholder('-'),
                TextColumn::make('status')->label('Status')->badge()
                    ->state(fn (GoodsReceiptFinding $record) => $record->waived_at ? 'Tetap terbuka (disetujui)' : GoodsReceiptFinding::STATUS_LABELS[$record->status])
                    ->color(fn (GoodsReceiptFinding $record) => $record->isResolved() ? 'gray' : 'danger'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Catat temuan')
                    ->modalHeading('Catat temuan')
                    ->visible(fn () => ! $this->getOwnerRecord()->isCompleted())
                    ->schema(self::formSchema($this->getOwnerRecord()->load('items')))
                    ->using(function (array $data, $livewire): Model {
                        $receipt = $this->getOwnerRecord();
                        $photos = $data['photos'] ?? [];
                        unset($data['photos']);

                        try {
                            $finding = app(ReceiptService::class)->addFinding($receipt, $data, auth()->user());
                        } catch (ReceiptRuleViolation $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            throw new Halt;
                        }

                        app(ReceiptService::class)->syncFiles($receipt, 'finding_photo', $photos, null, null, $finding->id);
                        $this->dispatch('refreshReceipt');

                        return $finding;
                    })
                    ->successNotificationTitle('Temuan dicatat.'),
            ])
            ->recordActions([
                Action::make('submit')
                    ->label('Ajukan')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (GoodsReceiptFinding $record) => $record->status === GoodsReceiptFinding::OPEN && ! $record->receipt->isCompleted())
                    ->action(fn (GoodsReceiptFinding $record) => $this->guard(fn () => app(ReceiptService::class)->submitFinding($record, auth()->user()))),
                Action::make('close')
                    ->label('Tutup')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (GoodsReceiptFinding $record) => auth()->user()->isAdmin() && ! $record->isResolved() && ! $record->receipt->isCompleted())
                    ->requiresConfirmation()
                    ->action(fn (GoodsReceiptFinding $record) => $this->guard(fn () => app(ReceiptService::class)->closeFinding($record, auth()->user()))),
                Action::make('waive')
                    ->label('Setujui tetap terbuka')
                    ->icon('heroicon-o-shield-check')
                    ->color('gray')
                    ->visible(fn (GoodsReceiptFinding $record) => auth()->user()->isAdmin() && ! $record->isResolved() && ! $record->receipt->isCompleted())
                    ->modalDescription('Penerimaan boleh dilanjutkan dan diselesaikan walau temuan ini belum selesai. Unit yang terkait dokumen akan berstatus Ditahan.')
                    ->schema([Textarea::make('reason')->label('Alasan')->required()->rows(2)])
                    ->action(fn (GoodsReceiptFinding $record, array $data) => $this->guard(fn () => app(ReceiptService::class)->waiveFinding($record, auth()->user(), $data['reason']))),
            ])
            ->emptyStateHeading('Belum ada temuan')
            ->emptyStateDescription('Catat temuan bila ada kemasan rusak, dokumen atau jumlah tidak cocok, aksesori kurang, atau unit cacat.');
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
            $this->dispatch('refreshReceipt');
        } catch (ReceiptRuleViolation $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
