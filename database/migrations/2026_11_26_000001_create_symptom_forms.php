<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personal data asked on the printed consultation form (page 1).
        Schema::table('customers', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('email')->nullable();
            $table->string('referred_by')->nullable();
        });

        // "Form gejala awal": printed pages 1-2 (consultation + health condition survey), one per lead.
        Schema::create('symptom_forms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('filled_by')->constrained('users')->restrictOnDelete();
            $table->date('filled_on');
            // "Diisi oleh tim sales" block.
            $table->date('activity_on')->nullable();
            $table->string('session_location')->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete(); // No. seri mesin = inventory ID
            $table->string('cup_serial')->nullable(); // No. seri gelas air
            $table->date('machine_taken_on')->nullable();
            // Health survey (page 2): checked items per group, free-text "Lainnya" per group.
            $table->json('conditions');
            $table->json('other_conditions')->nullable();
            $table->string('cancer_type')->nullable();
            $table->string('cancer_treatment')->nullable();
            $table->text('sales_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('symptom_forms');
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn(['birth_date', 'email', 'referred_by']));
    }
};
