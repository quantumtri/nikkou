<?php

namespace App\Filament\Resources\FollowupTemplates;

use App\Filament\Resources\Concerns\AdminOnly;
use App\Filament\Resources\FollowupTemplates\Pages\EditFollowupTemplate;
use App\Filament\Resources\FollowupTemplates\Pages\ListFollowupTemplates;
use App\Models\ActivityLog;
use App\Models\FollowupTemplate;
use App\Services\FollowupRenderer;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FollowupTemplateResource extends Resource
{
    use AdminOnly;

    protected static ?string $model = FollowupTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Follow-up';

    protected static ?string $navigationLabel = 'Teks follow-up';

    protected static ?string $modelLabel = 'teks follow-up';

    protected static ?string $pluralModelLabel = 'Teks follow-up';

    protected static ?string $slug = 'followup-templates';

    protected static ?int $navigationSort = 70;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Teks pesan')
                ->description('Teks ini dikirim langsung ke pelanggan lewat WhatsApp. Jangan menyebut alat menyembuhkan, mencegah, atau mengobati penyakit. Perangkat ini bukan obat.')
                ->schema([
                    Textarea::make('body')
                        ->label('Isi pesan')
                        ->required()
                        ->rows(5)
                        ->maxLength(1000)
                        ->helperText('Tempat isian: '.implode(', ', FollowupRenderer::PLACEHOLDERS).'. Setelah teks diubah, teks harus disetujui lagi sebelum dikirim.')
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                            $found = FollowupTemplate::forbiddenClaimsIn((string) $value);

                            if ($found !== []) {
                                $fail('Teks memuat kata yang tidak boleh dipakai karena terdengar sebagai klaim kesehatan: '.implode(', ', $found).'.');
                            }

                            preg_match_all('/\{[^}]*\}/', (string) $value, $used);
                            $unknown = array_diff($used[0], FollowupRenderer::PLACEHOLDERS);

                            if ($unknown !== []) {
                                $fail('Tempat isian tidak dikenal: '.implode(', ', $unknown).'.');
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->recordUrl(null)
            ->columns([
                TextColumn::make('title')->label('Langkah')->wrap()->description(fn (FollowupTemplate $record) => $record->group === 'system' ? 'Balasan sistem' : 'Peminjam home trial'),
                TextColumn::make('body')->label('Isi pesan')->wrap()->limit(120),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (FollowupTemplate $record) => self::statusLabel($record))
                    ->color(fn (FollowupTemplate $record) => $record->isSendable() ? 'info' : ($record->requires_review && $record->approved_at === null ? 'danger' : 'gray')),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah teks'),
                self::approveAction(),
                self::disableAction(),
            ]);
    }

    public static function statusLabel(FollowupTemplate $template): string
    {
        return match (true) {
            $template->isSendable() => 'Aktif',
            $template->requires_review && $template->approved_at === null => 'Belum disetujui',
            default => 'Nonaktif',
        };
    }

    private static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(fn (FollowupTemplate $record) => $record->requires_review ? 'Setujui dan aktifkan' : 'Aktifkan')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (FollowupTemplate $record) => ! $record->isSendable())
            ->requiresConfirmation()
            ->modalHeading('Aktifkan teks ini?')
            ->modalDescription(fn (FollowupTemplate $record) => $record->requires_review
                ? 'Pastikan teks sudah ditinjau (untuk balasan keluhan serius: oleh tenaga kesehatan). Setelah aktif, teks dikirim ke pelanggan.'
                : 'Teks ini akan dikirim ke pelanggan.')
            ->action(function (FollowupTemplate $record) {
                if (FollowupTemplate::forbiddenClaimsIn($record->body) !== []) {
                    Notification::make()->title('Teks memuat kata klaim kesehatan. Ubah dulu sebelum diaktifkan.')->danger()->send();

                    return;
                }

                $record->update(['is_active' => true, 'approved_at' => now(), 'approved_by_id' => auth()->id()]);
                ActivityLog::record($record, 'followup_template.approved', auth()->user(), notes: $record->title);
                Notification::make()->title('Teks aktif.')->success()->send();
            });
    }

    private static function disableAction(): Action
    {
        return Action::make('disable')
            ->label('Nonaktifkan')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (FollowupTemplate $record) => $record->is_active)
            ->requiresConfirmation()
            ->modalDescription('Pesan yang belum terkirim untuk langkah ini akan dilewati.')
            ->action(function (FollowupTemplate $record) {
                $record->update(['is_active' => false]);
                ActivityLog::record($record, 'followup_template.disabled', auth()->user(), notes: $record->title);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFollowupTemplates::route('/'),
            'edit' => EditFollowupTemplate::route('/{record}/edit'),
        ];
    }
}
