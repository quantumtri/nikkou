<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Sop;
use App\Models\SopStep;
use App\Services\SopDiagram;
use App\Services\SopService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Read-only SOP library for staff (spec 14.2): only active SOPs assigned to the user. */
class SopController extends Controller
{
    public function __construct(private SopService $sops) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Sales/Sops/Index', [
            'sops' => $this->sops->visibleTo($user)->map(fn (Sop $sop) => [
                'id' => $sop->id,
                'code' => $sop->code,
                'title' => $sop->title,
                'version' => $sop->activeVersion->number,
                'effective_from' => $sop->activeVersion->effective_from?->locale('id')->translatedFormat('j M Y'),
                'is_read' => $sop->activeVersion->acknowledgements()->where('user_id', $user->id)->exists(),
            ])->values(),
        ]);
    }

    public function show(Request $request, Sop $sop): Response
    {
        Gate::authorize('view', $sop);

        $version = $sop->activeVersion;
        abort_unless($version, 404);

        $doc = $this->sops->toDocument($version);
        $user = $request->user();

        return Inertia::render('Sales/Sops/Show', [
            'sop' => ['id' => $sop->id, 'code' => $sop->code, 'title' => $sop->title],
            'version' => [
                'number' => $version->number,
                'effective_from' => $version->effective_from?->locale('id')->translatedFormat('j M Y'),
            ],
            'steps' => collect($doc['steps'])->map(fn ($s) => $s + [
                'type_label' => SopStep::TYPE_LABELS[$s['type']] ?? $s['type'],
                'next' => collect($doc['edges'])->where('from', $s['key'])->map(fn ($e) => [
                    'label' => $e['label'],
                    'to' => collect($doc['steps'])->firstWhere('key', $e['to'])['title'] ?? $e['to'],
                ])->values()->all(),
            ])->values(),
            'forms' => $doc['forms'],
            'svg' => app(SopDiagram::class)->render($doc),
            'is_read' => $version->acknowledgements()->where('user_id', $user->id)->exists(),
            'can_acknowledge' => $this->sops->isAssigned($sop, $user),
        ]);
    }

    public function acknowledge(Request $request, Sop $sop): RedirectResponse
    {
        Gate::authorize('view', $sop);
        abort_unless($sop->activeVersion, 404);

        $this->sops->acknowledge($sop->activeVersion, $request->user());

        return back()->with('success', 'Terima kasih, konfirmasi baca tercatat.');
    }
}
