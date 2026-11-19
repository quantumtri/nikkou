<?php

namespace App\Filament\Pages;

use App\Models\AppSetting;
use App\Services\ReceiptPrinter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Company details printed on the goods-receipt check sheet. Admin only. */
class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Pengaturan';

    protected static ?string $title = 'Pengaturan';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 95;

    protected string $view = 'filament.pages.settings';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'company_name' => AppSetting::read(ReceiptPrinter::SETTING_COMPANY),
            'form_code' => AppSetting::read(ReceiptPrinter::SETTING_FORM_CODE),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Lembar periksa penerimaan')
                ->description('Teks di kaki halaman PDF. Bila kosong, tertulis tempat isian: '.ReceiptPrinter::DEFAULT_COMPANY.' dan '.ReceiptPrinter::DEFAULT_FORM_CODE.'.')
                ->schema([
                    TextInput::make('company_name')->label('Nama perusahaan')->maxLength(255),
                    TextInput::make('form_code')->label('Kode form dan revisi')->maxLength(100)->placeholder('Misal: FM-GDG-01 rev. 1'),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        AppSetting::write(ReceiptPrinter::SETTING_COMPANY, $state['company_name'] ?: null);
        AppSetting::write(ReceiptPrinter::SETTING_FORM_CODE, $state['form_code'] ?: null);

        Notification::make()->title('Pengaturan disimpan.')->success()->send();
    }

    protected function getFormActions(): array
    {
        return [Action::make('save')->label('Simpan')->submit('save')];
    }
}
