<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptFile;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\LoanService;
use App\Services\ReceiptService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Demo and UAT data only. All names are fictional; never seed real customer data.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo accounts share one password taken from SEED_PASSWORD (see .env.example).
        $password = Hash::make(env('SEED_PASSWORD', 'password'));

        $accounts = [
            ['Rose Admin', 'admin@nikkou.test', UserRole::Admin],
            ['Dewi Lestari', 'dewi@nikkou.test', UserRole::Sales],
            ['Rizky Pratama', 'rizky@nikkou.test', UserRole::Sales],
            ['Agus Setiawan', 'gudang@nikkou.test', UserRole::Warehouse],
        ];

        foreach ($accounts as $i => [$name, $email, $role]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'whatsapp_number' => '0812000000'.($i + 1),
                'is_active' => true,
                'email_verified_at' => now(),
            ]);
        }

        $admin = User::where('email', 'admin@nikkou.test')->firstOrFail();

        // Placeholder product types until the client confirms the list (spec 11.4).
        $components = ['Unit utama', 'Adaptor dan kabel daya', 'Selang', 'Kanula', 'Buku panduan'];
        $home = UnitType::updateOrCreate(['name' => 'H2 Home'], ['description' => 'Inhaler hidrogen untuk pemakaian di rumah.', 'default_components' => $components]);
        $pro = UnitType::updateOrCreate(['name' => 'H2 Pro'], ['description' => 'Inhaler hidrogen kapasitas lebih besar.', 'default_components' => $components]);

        for ($n = 1; $n <= 24; $n++) {
            $code = sprintf('NK-%04d', $n);

            if (Unit::where('code', $code)->exists()) {
                continue;
            }

            $unit = Unit::create([
                'code' => $code,
                'unit_type_id' => ($n % 4 === 0 ? $pro : $home)->id,
                'received_on' => today()->subDays(40 - $n),
                'created_by_id' => $admin->id,
                'forwarder' => 'PT Samudra Kargo Nusantara',
                'location' => 'Gudang Surabaya',
                'initial_notes' => 'Kondisi baik, segel utuh.',
            ]);

            $unit->components()->createMany(array_map(fn ($name) => ['name' => $name, 'is_present' => true], $components));

            ActivityLog::record($unit, 'unit.created', $admin, null, $unit->status->value, 'Masuk gudang.');
        }

        $this->seedLoans();
        $this->seedReceipt();
    }

    /** Sample loans go through LoanService so status, leads and logs match real usage. */
    private function seedLoans(): void
    {
        if (Loan::exists()) {
            return;
        }

        $loans = app(LoanService::class);
        $dewi = User::where('email', 'dewi@nikkou.test')->firstOrFail();
        $rizky = User::where('email', 'rizky@nikkou.test')->firstOrFail();

        // [unit, sales, type, started days ago, due in days, customer or event]
        $samples = [
            ['NK-0012', $dewi, 'home_trial', 3, 0, ['Sari Wulandari', '081230000101', 'Jl. Rungkut Asri Timur 12', 'Rungkut']],
            ['NK-0007', $dewi, 'event', 1, 2, 'Pameran Kesehatan Surabaya'],
            ['NK-0019', $rizky, 'home_trial', 6, -2, ['Budi Santoso', '081230000102', 'Jl. Dharmahusada Indah 45', 'Mulyorejo']],
            ['NK-0005', $rizky, 'home_trial', 2, 1, ['Hendra Kusuma', '081230000103', 'Jl. Ketintang Baru 8', 'Gayungan']],
            ['NK-0009', $dewi, 'home_trial', 1, 3, ['Lina Hartono', '081230000104', 'Jl. Manyar Kertoarjo 21', 'Gubeng']],
            ['NK-0015', $rizky, 'event', 0, 1, 'Showcase Hotel Majapahit'],
            ['NK-0021', $dewi, 'home_trial', 0, 4, ['Yohanes Wijaya', '081230000105', 'Jl. Raya Darmo Permai 3', 'Dukuh Pakis']],
        ];

        foreach ($samples as [$code, $sales, $type, $startedAgo, $dueIn, $who]) {
            $start = today()->subDays($startedAgo);

            $loans->create([
                'unit_id' => Unit::where('code', $code)->value('id'),
                'type' => $type,
                'starts_on' => $start->toDateString(),
                'due_on' => today()->addDays($dueIn)->toDateString(),
                'event_name' => is_string($who) ? $who : null,
                'customer' => is_array($who) ? [
                    'name' => $who[0],
                    'whatsapp_number' => $who[1],
                    'address' => $who[2],
                    'city' => 'Surabaya',
                    'district' => $who[3],
                ] : null,
                'consent_data' => true,
                'consent_whatsapp' => true,
            ], $sales, $start->copy()->setTime(10, 0));
        }
    }

    /** One goods receipt with 3 units: one passed, one failed, one with a finding. Waiting for the owner's approval. */
    private function seedReceipt(): void
    {
        if (GoodsReceipt::exists()) {
            return;
        }

        $service = app(ReceiptService::class);
        $warehouse = User::where('email', 'gudang@nikkou.test')->firstOrFail();
        $owner = User::where('email', 'admin@nikkou.test')->firstOrFail();
        $type = UnitType::where('name', 'H2 Home')->firstOrFail();

        $receipt = $service->create($warehouse, [
            'received_on' => today()->subDay()->toDateString(),
            'po_number' => 'PO-2026-118',
            'supplier' => 'PT Hidrogen Asia Demo',
            'forwarder' => 'PT Samudra Kargo Nusantara',
            'tracking_number' => 'BL-884421',
            'packages_expected' => 3,
        ]);
        $service->updateHeader($receipt, ['packages_received' => 3]);

        foreach (['packaging_photographed' => true, 'damage_noted' => false, 'documents_match' => true, 'model_quantity_ok' => true, 'import_documents' => true, 'delivery_note_signed' => true] as $code => $passed) {
            $service->setCheck($receipt, $code, $passed);
        }

        $service->syncFiles($receipt, GoodsReceiptFile::PACKAGE_PHOTO, [$this->demoImage('receipts/demo/kemasan.png', [200, 200, 200])]);
        $service->syncFiles($receipt, GoodsReceiptFile::DELIVERY_NOTE, [$this->demoImage('receipts/demo/surat-jalan.png', [230, 230, 230])]);
        foreach (['packing_list', 'invoice', 'bl_awb', 'pib'] as $document) {
            $service->syncFiles($receipt, GoodsReceiptFile::IMPORT_DOCUMENT, [$this->demoImage("receipts/demo/{$document}.png", [240, 240, 240])], null, $document);
        }

        $components = fn (bool $complete) => [
            ['name' => 'Unit utama', 'present' => true], ['name' => 'Adaptor dan kabel daya', 'present' => true],
            ['name' => 'Selang', 'present' => true], ['name' => 'Kanula', 'present' => $complete], ['name' => 'Buku panduan', 'present' => true],
        ];
        $good = ['powers_on' => true, 'outputs' => true, 'no_leak' => true, 'no_abnormal' => true];

        // 1. Passed.
        $a = $service->addItem($receipt, ['unit_type_id' => $type->id, 'serial_number' => 'H2H-26-0101', 'components' => $components(true), 'physical_ok' => true, 'notes' => 'Segel utuh.']);
        $service->recordTest($a, $good, $warehouse);

        // 2. Failed the function test.
        $b = $service->addItem($receipt, ['unit_type_id' => $type->id, 'serial_number' => 'H2H-26-0102', 'components' => $components(true), 'physical_ok' => true]);
        $service->recordTest($b, [...$good, 'outputs' => false, 'fail_reason' => 'Output gas lemah, tidak mencapai angka normal.'], $warehouse);

        // 3. Passed, but its carton arrived dented: a finding (closed by the owner after the photo was taken).
        $c = $service->addItem($receipt, ['unit_type_id' => $type->id, 'serial_number' => 'H2H-26-0103', 'components' => $components(true), 'physical_ok' => true, 'notes' => 'Kardus penyok, unit baik.']);
        $service->recordTest($c, $good, $warehouse);
        $finding = $service->addFinding($receipt, [
            'type' => 'damaged_packaging', 'description' => 'Kardus unit ke-3 penyok di sudut. Unit di dalam baik; difoto untuk klaim ke kurir.',
            'goods_receipt_item_id' => $c->id, 'action' => 'claim', 'responsible_id' => $warehouse->id,
        ], $warehouse);

        foreach ([$a, $b, $c] as $i => $item) {
            $service->syncFiles($receipt, GoodsReceiptFile::UNIT_PHOTO, [$this->demoImage("receipts/demo/unit-{$i}.png", [220 - $i * 15, 225, 235])], $item->id);
        }

        $service->closeFinding($finding, $owner);
        $service->saveUnits($receipt, $warehouse);
        foreach ([$a->refresh(), $b->refresh(), $c->refresh()] as $i => $item) {
            $service->setShelf($item, 'A1-0'.($i + 1), $warehouse);
        }
        $service->submitForApproval($receipt, $warehouse);
    }

    /** Small placeholder picture so demo links open something real. */
    private function demoImage(string $path, array $rgb): string
    {
        $image = imagecreatetruecolor(320, 220);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
        imagefilledrectangle($image, 40, 50, 200, 150, imagecolorallocate($image, 192, 38, 27));

        ob_start();
        imagepng($image);
        Storage::disk('local')->put($path, (string) ob_get_clean());

        return $path;
    }
}
