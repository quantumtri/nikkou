<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Enums\ProductCategory;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Services\FollowUpService;
use App\Services\InvoiceService;
use App\Services\LeadService;
use Illuminate\Database\Seeder;

/** Product catalog plus realistic sample leads for demo and UAT (fictional people). */
class LeadSeeder extends Seeder
{
    public function run(): void
    {
        // Placeholder prices (Rupiah) for demo; replace with the real catalog prices in Admin > Produk.
        $products = [
            ['H2 Home', ProductCategory::Main, 8900000], ['H2 Pro', ProductCategory::Main, 14900000],
            ['Kanula hidung', ProductCategory::Accessory, 150000], ['Selang cadangan', ProductCategory::Accessory, 250000],
            ['Adaptor daya', ProductCategory::Accessory, 350000], ['Tas pembawa', ProductCategory::Accessory, 450000],
        ];
        foreach ($products as [$name, $category, $price]) {
            Product::updateOrCreate(['name' => $name], [
                'category' => $category, 'price' => $price, 'is_active' => true,
                // Main products are sold from the unit type of the same name.
                'unit_type_id' => $category === ProductCategory::Main ? UnitType::where('name', $name)->value('id') : null,
            ]);
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
        $followUps = app(FollowUpService::class);
        $invoices = app(InvoiceService::class);
        $admin = User::where('email', 'admin@nikkou.test')->first();
        $id = fn (string $name) => Product::where('name', $name)->value('id');

        // [sales, name, city, province, gender, age, product, accessories, days ago, symptom form, status, follow-up responses (oldest first)]
        $rows = [
            [$dewi, 'Siti Rahmawati', 'Surabaya', 'Jawa Timur', 'female', '40-49', 'H2 Home', ['Kanula hidung'], 10, true, 'trial_active', ['neutral', 'positive', 'positive']],
            [$dewi, 'Bambang Hartono', 'Sidoarjo', 'Jawa Timur', 'male', '50-59', 'H2 Pro', [], 7, true, 'offer_sent', ['neutral', 'positive']],
            [$dewi, 'Ratna Kusumawati', 'Malang', 'Jawa Timur', 'female', '30-39', 'H2 Home', ['Selang cadangan'], 4, false, 'trial_active', ['neutral']],
            [$dewi, 'Agus Prasetyo', 'Gresik', 'Jawa Timur', 'male', '60+', 'H2 Home', ['Kanula hidung', 'Adaptor daya'], 12, true, 'purchased', ['neutral', 'positive', 'positive', 'positive']],
            [$rizky, 'Maria Gunawan', 'Denpasar', 'Bali', 'female', '40-49', 'H2 Home', [], 9, true, 'cancelled', ['neutral', 'negative', 'negative']],
            [$rizky, 'Hendrik Wijaya', 'Jakarta Selatan', 'DKI Jakarta', 'male', '30-39', 'H2 Pro', [], 3, false, 'new', []],
            [$rizky, 'Dewi Anggraini', 'Bandung', 'Jawa Barat', 'female', '20-29', 'H2 Home', ['Tas pembawa'], 6, true, 'trial_active', ['neutral', 'neutral']],
            [$rizky, 'Joko Santoso', 'Semarang', 'Jawa Tengah', 'male', '50-59', 'H2 Home', [], 14, true, 'offer_sent', ['positive', 'neutral', 'neutral', 'neutral']],
        ];

        foreach ($rows as [$sales, $name, $city, $province, $gender, $age, $product, $accessories, $daysAgo, $symptom, $status, $responses]) {
            $lead = $service->create($service->validate([
                'name' => $name, 'city' => $city, 'province' => $province, 'gender' => $gender, 'age_range' => $age,
                'path' => 'trial', 'product_id' => $id($product), 'trial_started_on' => today()->subDays($daysAgo)->toDateString(),
                'accessories' => array_map(fn ($a) => ['product_id' => $id($a), 'quantity' => 1], $accessories),
                'symptom_form_filled' => $symptom, 'status' => $status,
                'cancel_reason' => $status === 'cancelled' ? 'price_too_high' : null,
            ]), $sales);

            foreach ($responses as $i => $response) {
                $followUps->add($lead, $followUps->validate([
                    'followed_up_on' => today()->subDays(max(0, $daysAgo - 2 - $i * 2))->toDateString(),
                    'channel' => $i % 2 === 0 ? 'whatsapp' : 'call', 'response' => $response,
                    'notes' => $i === 0 ? 'Pemakaian hari pertama, belum ada keluhan.' : null,
                ]), $sales);
            }
        }

        // Direct purchases: no trial, the invoice is issued right away.
        $unitFor = fn (string $product) => Unit::where('unit_type_id', Product::where('name', $product)->value('unit_type_id'))
            ->where('status', 'available')->orderBy('code')->value('id');
        $direct = [
            [$dewi, 'Lestari Wulandari', 'Yogyakarta', 'DI Yogyakarta', 'female', '50-59', 'H2 Pro', ['Kanula hidung', 'Tas pembawa'], false],
            [$rizky, 'Budi Hartanto', 'Surabaya', 'Jawa Timur', 'male', '40-49', 'H2 Home', ['Selang cadangan'], true],
        ];
        foreach ($direct as [$sales, $name, $city, $province, $gender, $age, $product, $accessories, $paid]) {
            $lead = $service->create($service->validate([
                'name' => $name, 'city' => $city, 'province' => $province, 'gender' => $gender, 'age_range' => $age,
                'path' => 'direct', 'product_id' => $id($product), 'unit_id' => $unitFor($product),
                'accessories' => array_map(fn ($a) => ['product_id' => $id($a), 'quantity' => 1], $accessories),
                'symptom_form_filled' => false, 'status' => 'offer_sent',
            ]), $sales);

            if ($paid && $admin) {
                $invoices->changeStatus($lead->invoices()->first(), InvoiceStatus::Paid, $admin);
            }
        }
    }
}
