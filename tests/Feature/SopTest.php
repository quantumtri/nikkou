<?php

use App\Filament\Resources\Sops\Pages\EditSop;
use App\Filament\Resources\Sops\Pages\ViewSop;
use App\Models\ActivityLog;
use App\Models\Sop;
use App\Models\SopAssignment;
use App\Models\SopVersion;
use App\Models\User;
use App\Services\SopDiagram;
use App\Services\SopService;
use Database\Seeders\SopSeeder;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Rose Admin']);
    $this->gudang = User::factory()->warehouse()->create(['name' => 'Agus']);
    $this->dewi = User::factory()->sales()->create(['name' => 'Dewi']);
    $this->service = app(SopService::class);

    $this->doc = fn (array $override = []) => [
        'roles' => ['Admin gudang', 'Pemilik'],
        'steps' => [
            ['key' => 's1', 'number' => 1, 'type' => 'step', 'title' => 'Mulai kerja', 'description' => null, 'role' => 'Admin gudang', 'deadline' => null, 'related_route' => null],
            ['key' => 'd1', 'number' => null, 'type' => 'decision', 'title' => 'Cocok?', 'description' => null, 'role' => 'Admin gudang', 'deadline' => null, 'related_route' => null],
            ['key' => 't1', 'number' => null, 'type' => 'step', 'title' => 'Tahan barang', 'description' => null, 'role' => 'Pemilik', 'deadline' => 'Hari yang sama', 'related_route' => null],
            ['key' => 'end', 'number' => null, 'type' => 'end', 'title' => 'Selesai', 'description' => null, 'role' => 'Pemilik', 'deadline' => null, 'related_route' => null],
        ],
        'edges' => [
            ['from' => 's1', 'to' => 'd1', 'label' => null, 'type' => 'normal'],
            ['from' => 'd1', 'to' => 'end', 'label' => 'Cocok', 'type' => 'normal'],
            ['from' => 'd1', 'to' => 't1', 'label' => 'Tidak cocok', 'type' => 'problem'],
            ['from' => 't1', 'to' => 'end', 'label' => 'dicatat', 'type' => 'delayed'],
        ],
        'forms' => [['name' => 'Lembar periksa', 'type' => 'web', 'route_or_file' => '/admin/receipts/create', 'step' => 's1']],
        ...$override,
    ];

    $this->sop = Sop::create(['code' => 'SOP-99', 'title' => 'Uji', 'owner_id' => $this->admin->id]);
});

it('saves a draft version without making it active', function () {
    $v = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin, 'Awal');

    expect($v->number)->toBe(1)
        ->and($v->status)->toBe('draft')
        ->and($this->sop->refresh()->status)->toBe('draft')
        ->and($this->sop->active_version_id)->toBeNull()
        ->and($v->steps)->toHaveCount(4)
        ->and($v->edges)->toHaveCount(4)
        ->and($v->forms)->toHaveCount(1);
});

it('round-trips the document through the database', function () {
    $v = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);

    expect($this->service->toDocument($v))->toEqual(($this->doc)());
});

it('replaces the open draft instead of piling up versions', function () {
    $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $again = $this->service->saveDraft($this->sop, ($this->doc)(['roles' => ['Admin gudang', 'Pemilik', 'Penguji']]), $this->admin);

    expect($this->sop->versions()->count())->toBe(1)
        ->and($again->roles)->toHaveCount(3);
});

it('rejects flows that cannot be drawn', function (array $override, string $message) {
    expect(fn () => $this->service->saveDraft($this->sop, ($this->doc)($override), $this->admin))
        ->toThrow(ValidationException::class, $message);
})->with([
    'no end' => [['steps' => [['key' => 's1', 'number' => 1, 'type' => 'step', 'title' => 'A', 'role' => 'Admin gudang']], 'edges' => [], 'forms' => []], 'Selesai'],
    'edge to missing step' => [['edges' => [['from' => 's1', 'to' => 'zzz', 'label' => null, 'type' => 'normal']]], 'tidak ada'],
    'role missing' => [['roles' => ['Pemilik']], 'peran yang tidak ada'],
    'duplicate keys' => [['steps' => [
        ['key' => 's1', 'type' => 'step', 'title' => 'A'], ['key' => 's1', 'type' => 'end', 'title' => 'B'],
    ], 'edges' => [], 'forms' => []], 'kode unik'],
    'bad form' => [['forms' => [['name' => 'X', 'type' => 'web', 'route_or_file' => '', 'step' => null]]], 'belum lengkap'],
]);

it('lets only the owner approve, and keeps a single active version', function () {
    $v1 = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);

    expect(fn () => $this->service->publish($v1, $this->gudang))->toThrow(ValidationException::class);

    $this->service->publish($v1, $this->admin);
    $v2 = $this->service->saveDraft($this->sop, ($this->doc)(['roles' => ['Admin gudang', 'Pemilik', 'Penguji']]), $this->admin, 'Tambah penguji');
    expect($v2->number)->toBe(2);

    $this->service->publish($v2, $this->admin);

    $this->sop->refresh();
    expect($this->sop->status)->toBe('active')
        ->and($this->sop->active_version_id)->toBe($v2->id)
        ->and($this->sop->review_on)->not->toBeNull()
        ->and(SopVersion::where('sop_id', $this->sop->id)->where('status', 'active')->count())->toBe(1)
        ->and($v1->refresh()->status)->toBe('archived')
        ->and($v2->refresh()->approved_by_id)->toBe($this->admin->id);
});

it('does not approve a version twice', function () {
    $v = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v, $this->admin);

    expect(fn () => $this->service->publish($v->refresh(), $this->admin))->toThrow(ValidationException::class);
});

it('records save and approval in the audit log', function () {
    $v = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v, $this->admin);

    expect(ActivityLog::where('subject_type', 'sop')->where('subject_id', $this->sop->id)->pluck('action')->all())
        ->toBe(['sop.draft_saved', 'sop.approved']);
});

it('asks assigned staff, by role or by name, to read a newly approved version', function () {
    SopAssignment::create(['sop_id' => $this->sop->id, 'role' => 'warehouse']);
    SopAssignment::create(['sop_id' => $this->sop->id, 'user_id' => $this->dewi->id]);

    $v = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v, $this->admin);

    expect($this->gudang->notifications()->count())->toBe(1)
        ->and($this->dewi->notifications()->count())->toBe(1)
        ->and($this->admin->notifications()->count())->toBe(0)
        ->and($this->service->assignedUsers($this->sop)->pluck('id')->all())->toContain($this->gudang->id, $this->dewi->id);
});

it('confirms reading once per version and only for assigned staff', function () {
    SopAssignment::create(['sop_id' => $this->sop->id, 'role' => 'warehouse']);
    $v = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v, $this->admin);

    expect($this->service->pendingReaders($v)->pluck('id')->all())->toBe([$this->gudang->id]);

    expect(fn () => $this->service->acknowledge($v, $this->dewi))->toThrow(ValidationException::class);

    $this->service->acknowledge($v, $this->gudang);
    $this->service->acknowledge($v, $this->gudang);

    expect($v->acknowledgements()->count())->toBe(1)
        ->and($this->service->pendingReaders($v))->toBeEmpty()
        ->and($this->service->unreadFor($this->gudang))->toBeEmpty();
});

it('asks for a fresh confirmation when a new version is approved', function () {
    SopAssignment::create(['sop_id' => $this->sop->id, 'role' => 'warehouse']);
    $v1 = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v1, $this->admin);
    $this->service->acknowledge($v1, $this->gudang);

    $v2 = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v2, $this->admin);

    expect($this->service->unreadFor($this->gudang)->pluck('id')->all())->toBe([$this->sop->id]);
});

it('throttles automatic reminders', function () {
    SopAssignment::create(['sop_id' => $this->sop->id, 'role' => 'warehouse']);
    $this->service->publish($this->service->saveDraft($this->sop, ($this->doc)(), $this->admin), $this->admin);
    $before = $this->gudang->notifications()->count();

    expect($this->service->remindUnread())->toBe(1)
        ->and($this->service->remindUnread())->toBe(0)
        ->and($this->gudang->notifications()->count())->toBe($before + 1);
});

it('reminds admins when a review is due', function () {
    $this->service->publish($this->service->saveDraft($this->sop, ($this->doc)(), $this->admin), $this->admin);
    $this->sop->forceFill(['review_on' => today()->addDays(10)])->save();

    expect($this->service->remindReviews())->toBe(1);
});

it('compares two versions', function () {
    $v1 = $this->service->saveDraft($this->sop, ($this->doc)(), $this->admin);
    $this->service->publish($v1, $this->admin);

    $changed = ($this->doc)();
    $changed['steps'][0]['title'] = 'Mulai kerja baru';
    $changed['steps'][] = ['key' => 'x1', 'number' => null, 'type' => 'step', 'title' => 'Tambahan', 'description' => null, 'role' => 'Pemilik', 'deadline' => null, 'related_route' => null];
    $changed['edges'][] = ['from' => 't1', 'to' => 'x1', 'label' => null, 'type' => 'normal'];
    unset($changed['steps'][2]);
    $changed['steps'] = array_values($changed['steps']);
    $changed['edges'] = array_values(array_filter($changed['edges'], fn ($e) => $e['from'] !== 't1' || $e['to'] === 'x1'));
    $changed['edges'] = array_values(array_filter($changed['edges'], fn ($e) => $e['to'] !== 't1'));
    $changed['edges'] = array_values(array_filter($changed['edges'], fn ($e) => $e['from'] !== 't1'));

    $v2 = $this->service->saveDraft($this->sop, $changed, $this->admin);
    $diff = $this->service->compare($v1, $v2);
    $byKey = collect($diff['steps'])->keyBy('key');

    expect($byKey['s1']['change'])->toBe('changed')
        ->and($byKey['s1']['fields'])->toBe(['title'])
        ->and($byKey['x1']['change'])->toBe('added')
        ->and($byKey['t1']['change'])->toBe('removed')
        ->and($diff['edges'])->not->toBeEmpty();
});

it('draws the diagram from data: lanes, shapes and edge styles', function () {
    $svg = app(SopDiagram::class)->render(($this->doc)());

    expect($svg)->toStartWith('<svg')
        ->and(substr_count($svg, 'data-step='))->toBe(4)
        ->and($svg)->toContain('<polygon')                  // decision diamond
        ->and($svg)->toContain('class="edge problem"')
        ->and($svg)->toContain('class="edge delayed"')
        ->and($svg)->toContain('Admin gudang')
        ->and($svg)->toContain('node node-step problem');   // reached only by a problem edge
});

it('draws an empty flow without failing', function () {
    expect(app(SopDiagram::class)->render(['roles' => [], 'steps' => [], 'edges' => [], 'forms' => []]))->toContain('Belum ada langkah');
});

it('seeds SOP-01 from the spec plus nine empty drafts', function () {
    $this->seed(SopSeeder::class);

    $sop = Sop::where('code', 'SOP-01')->firstOrFail();
    $doc = $this->service->toDocument($sop->activeVersion);

    expect($sop->status)->toBe('active')
        ->and($doc['roles'])->toBe(['Admin gudang', 'Penguji', 'Pemilik / manajer'])
        ->and($doc['steps'])->toHaveCount(12)
        ->and($doc['edges'])->toHaveCount(13)
        ->and(collect($doc['edges'])->pluck('type')->unique()->sort()->values()->all())->toBe(['delayed', 'normal', 'problem'])
        ->and(collect($doc['steps'])->firstWhere('key', 's6')['related_route'])->toBe('/admin/receipts/create')
        ->and($doc['forms'])->toHaveCount(2)
        ->and(Sop::where('status', 'draft')->where('code', '!=', 'SOP-99')->count())->toBe(9)
        ->and(SopVersion::count())->toBe(1);

    // Running it again changes nothing.
    $this->seed(SopSeeder::class);
    expect(Sop::count())->toBe(11)->and(SopVersion::count())->toBe(1);
});

describe('admin screens', function () {
    it('lists SOPs for admin and shows the three tabs', function () {
        $this->seed(SopSeeder::class);
        $sop = Sop::where('code', 'SOP-01')->first();

        $this->actingAs($this->admin)->get('/admin/sops')->assertOk()->assertSee('Penerimaan inventori masuk')->assertSee('Peminjaman dan serah terima unit');

        $this->actingAs($this->admin);
        Livewire::test(ViewSop::class, ['record' => $sop->id])
            ->assertSee('Diagram alur')
            ->call('setTab', 'langkah')->assertSee('Persiapan')
            ->call('setTab', 'versi')->assertSee('Riwayat versi')->assertSee('Konfirmasi baca');
    });

    it('lets the owner edit, save a draft and approve from the editor', function () {
        $this->seed(SopSeeder::class);
        $sop = Sop::where('code', 'SOP-01')->first();
        $this->actingAs($this->admin);

        $component = Livewire::test(EditSop::class, ['record' => $sop->id]);
        $state = $component->get('data');
        $first = array_key_first($state['steps']);
        $state['steps'][$first]['title'] = 'Persiapan dan cek PO';
        $state['change_summary'] = 'Judul langkah 1';

        $component->set('data', $state)->call('save')->assertHasNoErrors();

        $draft = $sop->refresh()->draftVersion();
        expect($draft->number)->toBe(2)
            ->and($draft->steps()->where('key', 's1')->value('title'))->toBe('Persiapan dan cek PO')
            ->and($draft->edges()->count())->toBe(13)
            ->and($sop->active_version_id)->not->toBe($draft->id);

        Livewire::test(ViewSop::class, ['record' => $sop->id])->callAction('approve');

        expect($sop->refresh()->activeVersion->number)->toBe(2);
    });

    it('shows every diagram on one page, scoped by role', function () {
        $this->seed(SopSeeder::class);

        $this->actingAs($this->admin)->get('/admin/sop-diagrams')->assertOk()
            ->assertSee('SOP-01')->assertSee('data-step="s1"', false)->assertSee('SOP-02')->assertSee('Alur belum diisi');
        $this->actingAs($this->gudang)->get('/admin/sop-diagrams')->assertOk()
            ->assertSee('SOP-01')->assertDontSee('SOP-02');
        $this->actingAs($this->dewi)->get('/admin/sop-diagrams')->assertForbidden();
    });

    it('keeps warehouse and sales out of the editor', function () {
        $this->seed(SopSeeder::class);
        $sop = Sop::where('code', 'SOP-01')->first();

        $this->actingAs($this->gudang)->get("/admin/sops/{$sop->id}/edit")->assertForbidden();
        $this->actingAs($this->gudang)->get('/admin/sops/create')->assertForbidden();
        $this->actingAs($this->dewi)->get('/admin/sops')->assertForbidden();
    });

    it('shows warehouse only active assigned SOPs', function () {
        $this->seed(SopSeeder::class);
        $this->actingAs($this->gudang)->get('/admin/sops')->assertOk()
            ->assertSee('Penerimaan inventori masuk')->assertDontSee('Peminjaman dan serah terima unit');
    });
});

describe('sales app', function () {
    beforeEach(fn () => $this->seed(SopSeeder::class));

    it('lists the assigned active SOPs, read-only', function () {
        $this->actingAs($this->dewi)->get('/sales/sops')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Sales/Sops/Index')
            ->has('sops', 1)
            ->where('sops.0.code', 'SOP-01')
            ->where('sops.0.is_read', false));
    });

    it('opens an SOP with steps and diagram, and records the confirmation', function () {
        $sop = Sop::where('code', 'SOP-01')->first();

        $this->actingAs($this->dewi)->get("/sales/sops/{$sop->id}")->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Sales/Sops/Show')
            ->has('steps', 12)
            ->where('can_acknowledge', true)
            ->where('is_read', false));

        $this->actingAs($this->dewi)->post("/sales/sops/{$sop->id}/acknowledge")->assertRedirect();
        $this->actingAs($this->dewi)->post("/sales/sops/{$sop->id}/acknowledge")->assertRedirect();

        expect($sop->activeVersion->acknowledgements()->where('user_id', $this->dewi->id)->count())->toBe(1);
    });

    it('hides drafts and SOPs not assigned to the user', function () {
        $draft = Sop::where('code', 'SOP-02')->first();
        $this->actingAs($this->dewi)->get("/sales/sops/{$draft->id}")->assertForbidden();

        $sop = Sop::where('code', 'SOP-01')->first();
        SopAssignment::where('sop_id', $sop->id)->where('role', 'sales')->delete();
        $this->actingAs($this->dewi)->get("/sales/sops/{$sop->id}")->assertForbidden();
        $this->actingAs($this->dewi)->post("/sales/sops/{$sop->id}/acknowledge")->assertForbidden();
    });

    it('shows how many SOPs are still unread on the home screen', function () {
        $this->actingAs($this->dewi)->get('/sales')->assertInertia(fn (AssertableInertia $page) => $page->where('unreadSops', 1));
    });
});
