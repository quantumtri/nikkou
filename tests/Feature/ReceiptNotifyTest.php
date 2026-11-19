<?php

use App\Mail\ReceiptSummaryMail;
use App\Models\GoodsReceiptFile;
use App\Models\GoodsReceiptFinding;
use App\Models\UnitType;
use App\Models\User;
use App\Services\ReceiptNotifier;
use App\Services\ReceiptService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->service = app(ReceiptService::class);
    $this->warehouse = User::factory()->warehouse()->create(['name' => 'Agus Gudang']);
    $this->owner = User::factory()->admin()->create(['name' => 'Rose Pemilik', 'email' => 'rose@nikkou.test', 'whatsapp_number' => '6281230000001']);
    $this->type = UnitType::factory()->create();
});

it('sends the summary to the owners in the app, by email and as a queued WhatsApp message when submitted', function () {
    Mail::fake();
    $inactive = User::factory()->admin()->inactive()->create();
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString(), 'supplier' => 'PT Hidrogen Asia', 'po_number' => 'PO-5']);
    $item = $this->service->addItem($receipt, ['unit_type_id' => $this->type->id, 'serial_number' => 'S1', 'components' => [['name' => 'Unit utama', 'present' => true]], 'physical_ok' => true, 'shelf' => 'A1']);
    GoodsReceiptFile::create(['goods_receipt_id' => $receipt->id, 'goods_receipt_item_id' => $item->id, 'kind' => 'unit_photo', 'file_path' => 'u.jpg']);
    $this->service->recordTest($item, ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true], $this->warehouse);
    $this->service->saveUnits($receipt, $this->warehouse);

    $this->service->submitForApproval($receipt, $this->warehouse);

    Mail::assertSent(ReceiptSummaryMail::class, fn ($mail) => $mail->hasTo('rose@nikkou.test') && str_contains($mail->summary, 'Lolos: 1'));
    Mail::assertSent(ReceiptSummaryMail::class, 1);
    expect(DB::table('notifications')->where('notifiable_id', $this->owner->id)->count())->toBe(1)
        ->and(DB::table('notifications')->where('notifiable_id', $inactive->id)->count())->toBe(0);

    $queued = DB::table('admin_message_queue')->sole();
    expect($queued)->channel->toBe('whatsapp')->user_id->toBe($this->owner->id)->status->toBe('pending')
        ->and($queued->body)->toContain($receipt->number)->toContain('/admin/receipts/'.$receipt->id);
    expect($receipt->refresh()->summary_sent_at)->not->toBeNull();
});

it('keeps the submission even when the email cannot be sent', function () {
    Mail::shouldReceive('to')->andThrow(new RuntimeException('SMTP down'));
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);

    app(ReceiptNotifier::class)->submitted($receipt);

    expect(DB::table('notifications')->where('notifiable_id', $this->owner->id)->count())->toBe(1)
        ->and(DB::table('admin_message_queue')->count())->toBe(1);
});

it('alerts the owners once a day about findings past their due date', function () {
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);
    $overdue = $this->service->addFinding($receipt, ['type' => 'accessories_missing', 'description' => 'Selang kurang', 'due_on' => today()->subDays(3)->toDateString(), 'responsible_id' => $this->warehouse->id], $this->warehouse);
    $this->service->addFinding($receipt, ['type' => 'unit_defect', 'description' => 'Belum jatuh tempo', 'due_on' => today()->addDay()->toDateString()], $this->warehouse);
    $closed = $this->service->addFinding($receipt, ['type' => 'damaged_packaging', 'description' => 'Sudah selesai', 'due_on' => today()->subDays(2)->toDateString()], $this->warehouse);
    $this->service->closeFinding($closed, $this->owner);

    $this->artisan('receipts:notify-overdue')->expectsOutput('1 temuan terlambat diberitahukan.')->assertSuccessful();

    $row = DB::table('notifications')->where('notifiable_id', $this->owner->id)->sole();
    expect($row->data)->toContain('Tinggi')->toContain('Aksesori kurang')->toContain($receipt->number)->toContain('Agus Gudang');
    expect($overdue->refresh()->overdue_notified_at)->not->toBeNull();

    // Same day: not repeated. A day later: again.
    $this->artisan('receipts:notify-overdue')->expectsOutput('0 temuan terlambat diberitahukan.');
    $this->travel(25)->hours();
    $this->artisan('receipts:notify-overdue')->expectsOutput('1 temuan terlambat diberitahukan.');
});

it('does not alert for findings the owner agreed to keep open', function () {
    $receipt = $this->service->create($this->warehouse, ['received_on' => today()->toDateString()]);
    $finding = $this->service->addFinding($receipt, ['type' => 'documents_incomplete', 'description' => 'PIB', 'due_on' => today()->subDay()->toDateString()], $this->warehouse);
    $this->service->waiveFinding($finding, $this->owner, 'PIB menyusul');

    expect(app(ReceiptNotifier::class)->notifyOverdueFindings())->toBe(0);
    expect(GoodsReceiptFinding::count())->toBe(1);
});

it('registers the overdue check with the scheduler', function () {
    $events = collect(app(Schedule::class)->events())->map->command;

    expect($events->contains(fn ($c) => str_contains((string) $c, 'receipts:notify-overdue')))->toBeTrue();
});
