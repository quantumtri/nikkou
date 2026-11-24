<?php

namespace Database\Seeders;

use App\Enums\ProductCategory;
use App\Models\Lead;
use App\Models\Product;
use App\Models\User;
use App\Services\LeadService;
use Illuminate\Database\Seeder;

/** Product catalog plus realistic sample leads for demo and UAT (fictional people). */
class LeadSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            ['H2 Home', ProductCategory::Main], ['H2 Pro', ProductCategory::Main],
            ['Kanula hidung', ProductCategory::Accessory], ['Selang cadangan', ProductCategory::Accessory],
            ['Adaptor daya', ProductCategory::Accessory], ['Tas pembawa', ProductCategory::Accessory],
        ];
        foreach ($products as [$name, $category]) {
            Product::updateOrCreate(['name' => $name], ['category' => $category, 'is_active' => true]);
        }

        if (Lead::where('source', 'manual')->exists()) {
            return;
        }

        $dewi = User::where('email', 'dewi@nikkou.test')->first();
        $rizky = User::where('email', 'rizky@nikkou.test')->first();
        if (! $dewi || ! $rizky) {
            return;
        }

        $service = app(LeadService::class);
        $id = fn (string $name) => Product::where('name', $name)->value('id');

        $rows = [
            [$dewi, 'Siti Rahmawati', 'Surabaya', 'Jawa Timur', 'female', '40-49', 'H2 Home', 10, true, 'trial_active', 3, 'positive'],
            [$dewi, 'Bambang Hartono', 'Sidoarjo', 'Jawa Timur', 'male', '50-59', 'H2 Pro', 7, true, 'offer_sent', 2, 'positive'],
            [$dewi, 'Ratna Kusumawati', 'Malang', 'Jawa Timur', 'female', '30-39', 'H2 Home', 4, false, 'trial_active', 1, null],
            [$dewi, 'Agus Prasetyo', 'Gresik', 'Jawa Timur', 'male', '60+', 'Kanula hidung', 12, true, 'purchased', 4, 'positive'],
            [$rizky, 'Maria Gunawan', 'Denpasar', 'Bali', 'female', '40-49', 'H2 Home', 9, true, 'cancelled', 3, 'negative'],
            [$rizky, 'Hendrik Wijaya', 'Jakarta Selatan', 'DKI Jakarta', 'male', '30-39', 'H2 Pro', 3, false, 'new', 0, null],
            [$rizky, 'Dewi Anggraini', 'Bandung', 'Jawa Barat', 'female', '20-29', 'Tas pembawa', 6, true, 'trial_active', 2, 'neutral'],
            [$rizky, 'Joko Santoso', 'Semarang', 'Jawa Tengah', 'male', '50-59', 'H2 Home', 14, true, 'offer_sent', 4, 'neutral'],
        ];

        foreach ($rows as [$sales, $name, $city, $province, $gender, $age, $product, $daysAgo, $symptom, $status, $followups, $response]) {
            $service->create($service->validate([
                'name' => $name, 'city' => $city, 'province' => $province, 'gender' => $gender, 'age_range' => $age,
                'product_id' => $id($product), 'trial_started_on' => today()->subDays($daysAgo)->toDateString(),
                'symptom_form_filled' => $symptom, 'status' => $status, 'followup_count' => $followups, 'overall_response' => $response,
                'cancel_reason' => $status === 'cancelled' ? 'price_too_high' : null,
            ]), $sales);
        }
    }
}
