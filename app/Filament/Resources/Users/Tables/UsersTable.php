<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Exceptions\AccountRuleViolation;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->searchPlaceholder('Cari nama atau email')
            ->recordUrl(fn (User $record) => UserResource::getUrl('edit', ['record' => $record]))
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->formatStateUsing(fn (UserRole $state) => $state->label()),
                TextColumn::make('email')
                    ->label('Email / WhatsApp')
                    ->description(fn (User $record) => $record->whatsapp_number ? '+'.$record->whatsapp_number : null)
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (User $record) => UserService::statusLabel(app(UserService::class)->status($record)))
                    ->color(fn (User $record) => match (app(UserService::class)->status($record)) {
                        UserService::STATUS_ACTIVE => 'info',
                        UserService::STATUS_INVITED => 'gray',
                        default => 'danger',
                    }),
            ])
            ->filters([
                SelectFilter::make('role')->label('Peran')->placeholder('Semua peran')->options(UserRole::options()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder('Semua status')
                    ->options(['active' => 'Aktif', 'invited' => 'Diundang', 'inactive' => 'Nonaktif'])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'inactive' => $query->where('is_active', false),
                        'invited' => $query->where('is_active', true)->whereNotNull('invited_at')->whereNull('email_verified_at'),
                        'active' => $query->where('is_active', true)->where(fn (Builder $q) => $q->whereNull('invited_at')->orWhereNotNull('email_verified_at')),
                        default => $query,
                    }),
            ])
            ->recordActions([
                EditAction::make()->label('Ubah'),
                self::linkAction(),
                self::toggleActiveAction(),
            ]);
    }

    /** Issues a fresh set-password link: invitation for new accounts, reset for existing ones. */
    public static function linkAction(): Action
    {
        return Action::make('link')
            ->label(fn (User $record) => app(UserService::class)->status($record) === UserService::STATUS_INVITED ? 'Kirim ulang undangan' : 'Atur ulang kata sandi')
            ->icon('heroicon-o-link')
            ->visible(fn (User $record) => $record->is_active)
            ->requiresConfirmation()
            ->modalHeading('Terbitkan tautan baru?')
            ->modalDescription('Tautan lama untuk akun ini berhenti berlaku. Kirim tautan baru hanya ke orangnya langsung.')
            ->action(function (User $record) {
                self::announceLink($record, app(UserService::class)->issueLink($record, auth()->user()));
            });
    }

    public static function toggleActiveAction(): Action
    {
        return Action::make('toggleActive')
            ->label(fn (User $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
            ->icon(fn (User $record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
            ->color(fn (User $record) => $record->is_active ? 'danger' : 'gray')
            ->visible(fn (User $record) => ! $record->is(auth()->user()))
            ->requiresConfirmation()
            ->modalHeading(fn (User $record) => $record->is_active ? "Nonaktifkan {$record->name}?" : "Aktifkan {$record->name}?")
            ->modalDescription(fn (User $record) => $record->is_active
                ? 'Akun ini langsung keluar dari semua perangkat dan tidak bisa masuk lagi. Datanya tetap tersimpan.'
                : 'Akun ini bisa masuk lagi dengan kata sandi yang sudah ada.')
            ->action(function (User $record) {
                $service = app(UserService::class);

                try {
                    $record->is_active ? $service->deactivate($record, auth()->user()) : $service->activate($record, auth()->user());
                } catch (AccountRuleViolation $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title($record->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.')->success()->send();
            });
    }

    /** Shows the link once, with a WhatsApp shortcut. Nothing is emailed: the link is shared by the admin. */
    public static function announceLink(User $user, string $link): void
    {
        $service = app(UserService::class);

        Notification::make()
            ->title("Tautan atur kata sandi untuk {$user->name}")
            ->body('Berlaku '.UserService::LINK_DAYS." hari dan hanya bisa dipakai sekali. Salin dan kirim langsung ke orangnya:\n\n{$link}")
            ->success()
            ->persistent()
            ->actions([
                Action::make('copy')->label('Salin tautan')->alpineClickHandler('window.navigator.clipboard.writeText('.json_encode($link).')')->button()->color('gray'),
                Action::make('whatsapp')->label('Kirim lewat WhatsApp')->url($service->whatsappInviteUrl($user, $link), shouldOpenInNewTab: true)->button(),
            ])
            ->send();
    }
}
