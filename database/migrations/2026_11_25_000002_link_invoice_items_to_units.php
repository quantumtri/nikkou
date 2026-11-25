<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A main product maps to the inventory unit type it is sold from (accessories stay unlinked).
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_type_id')->nullable()->after('category')->constrained('unit_types')->nullOnDelete();
        });

        // Sold main products point at the physical unit (its inventory ID).
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('unit_id')->nullable()->after('product_id')->constrained('units')->restrictOnDelete();
        });

        foreach (DB::table('products')->where('category', 'main')->get(['id', 'name']) as $product) {
            $typeId = DB::table('unit_types')->where('name', $product->name)->value('id');
            if ($typeId) {
                DB::table('products')->where('id', $product->id)->update(['unit_type_id' => $typeId]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('unit_id'));
        Schema::table('products', fn (Blueprint $t) => $t->dropConstrainedForeignId('unit_type_id'));
    }
};
