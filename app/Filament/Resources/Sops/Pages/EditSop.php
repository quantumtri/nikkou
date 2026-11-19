<?php

namespace App\Filament\Resources\Sops\Pages;

use App\Filament\Resources\Sops\SopResource;
use App\Models\Sop;
use App\Models\SopEdge;
use App\Models\SopForm;
use App\Models\SopStep;
use App\Services\SopDiagram;
use App\Services\SopService;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Form-based SOP editor. The form edits a plain document (see SopService); saving
 * writes a draft version and the diagram is always drawn from that data.
 */
class EditSop extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SopResource::class;

    protected string $view = 'filament.sops.edit';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(auth()->user()->can('update', $this->record), 403);

        $this->form->fill($this->stateFromDocument($this->startingDocument()));
    }

    public function getTitle(): string
    {
        return 'Ubah '.$this->record->code;
    }

    public function getSubheading(): ?string
    {
        return $this->record->title.' | Setiap simpan menjadi versi draf yang perlu disetujui pemilik.';
    }

    /** The open draft if any, otherwise a copy of the active (or newest) version. */
    private function startingDocument(): array
    {
        /** @var Sop $sop */
        $sop = $this->record;
        $version = $sop->draftVersion() ?? $sop->activeVersion ?? $sop->versions()->first();

        return $version
            ? app(SopService::class)->toDocument($version)
            : ['roles' => [], 'steps' => [], 'edges' => [], 'forms' => []];
    }

    /** @param  array<string, mixed>  $doc */
    private function stateFromDocument(array $doc): array
    {
        $edges = collect($doc['edges']);

        return [
            'change_summary' => null,
            'roles' => collect($doc['roles'])->map(fn ($name) => ['name' => $name])->all(),
            'steps' => collect($doc['steps'])->map(fn ($s) => $s + [
                'next' => $edges->where('from', $s['key'])->map(fn ($e) => ['to' => $e['to'], 'label' => $e['label'], 'type' => $e['type']])->values()->all(),
            ])->all(),
            'forms' => $doc['forms'],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function documentFromState(array $state): array
    {
        $steps = collect($state['steps'] ?? [])->values();
        $edges = [];
        foreach ($steps as $s) {
            foreach ($s['next'] ?? [] as $e) {
                $edges[] = ['from' => $s['key'] ?? '', 'to' => $e['to'] ?? '', 'label' => ($e['label'] ?? '') !== '' ? $e['label'] : null, 'type' => $e['type'] ?? SopEdge::NORMAL];
            }
        }

        return [
            'roles' => collect($state['roles'] ?? [])->pluck('name')->map(fn ($n) => trim((string) $n))->filter()->values()->all(),
            'steps' => $steps->map(fn ($s) => [
                'key' => trim((string) ($s['key'] ?? '')),
                'number' => ($s['number'] ?? '') !== '' && ($s['number'] ?? null) !== null ? (int) $s['number'] : null,
                'type' => $s['type'] ?? SopStep::STEP,
                'title' => (string) ($s['title'] ?? ''),
                'description' => ($s['description'] ?? '') !== '' ? $s['description'] : null,
                'role' => ($s['role'] ?? '') !== '' ? $s['role'] : null,
                'deadline' => ($s['deadline'] ?? '') !== '' ? $s['deadline'] : null,
                'related_route' => ($s['related_route'] ?? '') !== '' ? $s['related_route'] : null,
            ])->all(),
            'edges' => $edges,
            'forms' => collect($state['forms'] ?? [])->map(fn ($f) => [
                'name' => (string) ($f['name'] ?? ''),
                'type' => $f['type'] ?? '',
                'route_or_file' => (string) ($f['route_or_file'] ?? ''),
                'step' => ($f['step'] ?? '') !== '' ? $f['step'] : null,
            ])->all(),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $roleOptions = fn (Component $livewire) => collect($livewire->data['roles'] ?? [])->pluck('name')->filter()->mapWithKeys(fn ($n) => [$n => $n])->all();
        $stepOptions = fn (Component $livewire) => collect($livewire->data['steps'] ?? [])->filter(fn ($s) => ($s['key'] ?? '') !== '')
            ->mapWithKeys(fn ($s) => [$s['key'] => ($s['key']).' - '.(($s['title'] ?? '') ?: 'tanpa judul')])->all();

        return $schema->statePath('data')->components([
            Section::make('Peran (jalur diagram)')
                ->description('Setiap peran menjadi satu jalur di diagram, urut dari atas.')
                ->schema([
                    Repeater::make('roles')->label('Peran')->hiddenLabel()
                        ->schema([TextInput::make('name')->label('Nama peran')->required()->maxLength(80)->live(onBlur: true)])
                        ->reorderable()->addActionLabel('Tambah peran')->defaultItems(0),
                ]),

            Section::make('Langkah')
                ->description('Urutan di sini menentukan urutan di tabel. Tiap langkah menyebut ke mana alur berlanjut.')
                ->schema([
                    Repeater::make('steps')->hiddenLabel()
                        ->itemLabel(fn (array $state) => trim(($state['key'] ?? '').' '.($state['number'] ?? '').' '.($state['title'] ?? '')) ?: 'Langkah baru')
                        ->collapsible()->reorderable()->cloneable()->addActionLabel('Tambah langkah')->defaultItems(0)
                        ->schema([
                            TextInput::make('key')->label('Kode')->required()->maxLength(30)->alphaDash()->live(onBlur: true)
                                ->default(fn (Component $livewire) => 's'.(count($livewire->data['steps'] ?? []) + 1)),
                            Select::make('type')->label('Jenis')->options(SopStep::TYPE_LABELS)->default(SopStep::STEP)->required()->selectablePlaceholder(false)->live(),
                            TextInput::make('number')->label('Nomor')->numeric()->minValue(1)->helperText('Lencana angka di diagram. Kosongkan jika tidak bernomor.')
                                ->visible(fn ($get) => $get('type') === SopStep::STEP),
                            TextInput::make('title')->label('Judul')->required()->maxLength(255)->columnSpanFull()->live(onBlur: true),
                            Select::make('role')->label('Peran')->options($roleOptions)->live(onBlur: true),
                            TextInput::make('deadline')->label('Batas waktu')->maxLength(100)->placeholder('Contoh: 1 hari kerja'),
                            Textarea::make('description')->label('Uraian')->rows(2)->columnSpanFull(),
                            TextInput::make('related_route')->label('Layar terkait')->maxLength(255)->placeholder('/admin/receipts/create')->columnSpanFull(),
                            Repeater::make('next')->label('Lanjut ke')->columnSpanFull()
                                ->addActionLabel('Tambah panah')->defaultItems(0)->columns(3)
                                ->schema([
                                    Select::make('to')->label('Ke langkah')->options($stepOptions)->required(),
                                    TextInput::make('label')->label('Label')->maxLength(60)->placeholder('Contoh: Cocok'),
                                    Select::make('type')->label('Jenis')->options(SopEdge::TYPE_LABELS)->default(SopEdge::NORMAL)->required()->selectablePlaceholder(false),
                                ]),
                        ])->columns(3),
                ]),

            Section::make('Formulir terkait')
                ->schema([
                    Repeater::make('forms')->hiddenLabel()->addActionLabel('Tambah formulir')->defaultItems(0)->columns(2)
                        ->schema([
                            TextInput::make('name')->label('Nama')->required()->maxLength(255),
                            Select::make('type')->label('Jenis')->options(SopForm::TYPE_LABELS)->required(),
                            TextInput::make('route_or_file')->label('Rute atau nama berkas')->required()->maxLength(255),
                            Select::make('step')->label('Terkait langkah')->options($stepOptions)->placeholder('Seluruh SOP'),
                        ]),
                ]),

            Textarea::make('change_summary')->label('Catatan perubahan')->rows(2)->maxLength(1000)
                ->placeholder('Apa yang diubah dan kenapa'),
        ]);
    }

    /** Diagram preview drawn from the form as it is right now. */
    public function previewSvg(): string
    {
        return app(SopDiagram::class)->render($this->documentFromState($this->data ?? []));
    }

    public function save(bool $andApprove = false): void
    {
        $state = $this->form->getState();
        $service = app(SopService::class);

        try {
            $draft = $service->saveDraft($this->record, $this->documentFromState($state), auth()->user(), $state['change_summary'] ?? null);
            if ($andApprove) {
                $service->publish($draft, auth()->user());
            }
        } catch (ValidationException $e) {
            Notification::make()->title('Alur belum bisa disimpan')->body(collect($e->errors())->flatten()->implode("\n"))->danger()->send();

            return;
        }

        Notification::make()->title($andApprove ? 'Disimpan dan disetujui sebagai versi '.$draft->number : 'Draf versi '.$draft->number.' disimpan')->success()->send();
        $this->redirect(SopResource::getUrl('view', ['record' => $this->record]), navigate: false);
    }

    public function renumber(): void
    {
        $n = 1;
        $steps = collect($this->data['steps'] ?? [])->map(function ($s) use (&$n) {
            if (($s['type'] ?? null) === SopStep::STEP && ($s['number'] ?? '') !== '' && ($s['number'] ?? null) !== null) {
                $s['number'] = $n++;
            }

            return $s;
        })->all();
        $this->data['steps'] = $steps;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')->label('Kembali')->outlined()->url(fn () => SopResource::getUrl('view', ['record' => $this->record])),
        ];
    }
}
