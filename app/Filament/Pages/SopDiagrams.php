<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Sops\SopResource;
use App\Models\Sop;
use App\Models\SopVersion;
use App\Services\SopDiagram;
use App\Services\SopService;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/** All SOP diagrams on one page (active version; admin also sees the open draft of SOPs not yet approved). */
class SopDiagrams extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static ?string $navigationLabel = 'Semua diagram SOP';

    protected static string|\UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $title = 'Semua diagram SOP';

    protected static ?string $slug = 'sop-diagrams';

    protected static ?int $navigationSort = 51;

    protected ?string $subheading = 'Alur seluruh SOP dalam satu halaman. Klik judul untuk membuka detailnya.';

    protected string $view = 'filament.sops.diagrams';

    public static function canAccess(): bool
    {
        return SopResource::canViewAny();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $service = app(SopService::class);
        $diagram = app(SopDiagram::class);

        $items = SopResource::getEloquentQuery()->where('status', '!=', Sop::ARCHIVED)->orderBy('code')->get()
            ->map(function (Sop $sop) use ($service, $diagram) {
                $version = $sop->activeVersion ?? $sop->versions()->where('status', SopVersion::DRAFT)->first();
                $doc = $version ? $service->toDocument($version) : null;
                $hasFlow = $doc && count($doc['steps']) > 0;

                return [
                    'sop' => $sop,
                    'version' => $version,
                    'svg' => $hasFlow ? $diagram->render($doc) : null,
                    'url' => SopResource::getUrl('view', ['record' => $sop]),
                ];
            });

        return ['items' => $items];
    }
}
