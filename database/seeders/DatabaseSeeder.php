<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Loan;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\LoanService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

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
}
