<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
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
    }
}
