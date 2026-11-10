<?php

namespace App\Filament\Pages;

use App\Models\Unit;
use App\Services\LabelService;
use BackedEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

class Labels extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $navigationLabel = 'Label QR';

    protected static ?string $title = 'Label QR / barcode';

    protected static ?string $slug = 'labels';

    protected static ?int $navigationSort = 60;

    protected ?string $subheading = 'Dibuat dari data inventori. Pilih unit, atur ukuran, lalu cetak.';

    protected string $view = 'filament.pages.labels';

    /** Comma-separated unit ids preselected from the inventory list or a unit page. */
    #[Url(as: 'units')]
    public string $preselected = '';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()->can('printLabel', Unit::class);
    }

    public function mount(): void
    {
        $ids = collect(explode(',', $this->preselected))->filter(fn ($id) => ctype_digit($id))->map(fn ($id) => (int) $id);

        $this->form->fill([
            'unit_ids' => Unit::query()->whereKey($ids)->pluck('id')->all(),
            'mode' => LabelService::DEFAULT_MODE,
            'size' => LabelService::DEFAULT_SIZE,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                CheckboxList::make('unit_ids')
                    ->label('Pilih unit')
                    ->options(fn () => Unit::query()->orderByDesc('code')->pluck('code', 'id'))
                    ->descriptions(fn () => Unit::query()->get(['id', 'label_printed_at'])
                        ->mapWithKeys(fn (Unit $unit) => [$unit->id => $unit->label_printed_at ? 'Sudah dicetak' : 'Belum dicetak']))
                    ->searchable()
                    ->searchPrompt('Cari kode unit')
                    ->bulkToggleable()
                    ->columns(2)
                    ->required()
                    ->live()
                    ->validationMessages(['required' => 'Pilih minimal satu unit.']),
                Select::make('mode')
                    ->label('Jenis kode')
                    ->options(LabelService::CODE_MODES)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live(),
                Select::make('size')
                    ->label('Ukuran label')
                    ->options(LabelService::sizeOptions())
                    ->required()
                    ->selectablePlaceholder(false)
                    ->live(),
            ]);
    }

    public function selectedCount(): int
    {
        return count($this->data['unit_ids'] ?? []);
    }

    /** @return Collection<int, array{code: string, type: string, qr: ?string, barcode: ?string}> */
    public function previewLabels(): Collection
    {
        $mode = isset(LabelService::CODE_MODES[$this->data['mode'] ?? '']) ? $this->data['mode'] : LabelService::DEFAULT_MODE;
        $service = app(LabelService::class);

        // The preview shows the first few labels only; the PDF contains all of them.
        return Unit::query()
            ->with('type')
            ->whereKey($this->data['unit_ids'] ?? [])
            ->orderBy('code')
            ->limit(6)
            ->get()
            ->map(fn (Unit $unit) => $service->labelData($unit, $mode));
    }

    /** @return array{0: int, 1: int} */
    public function previewSize(): array
    {
        return LabelService::SIZES[$this->data['size'] ?? ''] ?? LabelService::SIZES[LabelService::DEFAULT_SIZE];
    }

    public function printLabels(): void
    {
        $this->generate(download: false);
    }

    public function downloadLabels(): void
    {
        $this->generate(download: true);
    }

    private function generate(bool $download): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $units = Unit::query()->whereKey($state['unit_ids'])->get();

        app(LabelService::class)->markPrinted($units, auth()->user());

        $url = route('labels.pdf', [
            'units' => $units->pluck('id')->implode(','),
            'size' => $state['size'],
            'mode' => $state['mode'],
            'download' => $download ? 1 : null,
        ]);

        Notification::make()->title($units->count().' label ditandai sudah dicetak.')->success()->send();

        $download
            ? $this->redirect($url)
            : $this->js('window.open('.json_encode($url).', "_blank", "noopener")');
    }
}
