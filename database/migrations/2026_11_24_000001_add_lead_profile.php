<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category', 12)->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('province')->nullable()->after('city');
            $table->string('gender', 8)->nullable();
            $table->string('age_range', 8)->nullable();
            // A manually added lead may not have a WhatsApp number yet.
            $table->string('whatsapp_number', 20)->nullable()->change();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->date('trial_started_on')->nullable();
            // Placeholder until the symptom form exists; it will link to that form.
            $table->boolean('symptom_form_filled')->default(false);
            $table->unsignedSmallInteger('followup_count')->default(0);
            $table->string('overall_response', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn(['trial_started_on', 'symptom_form_filled', 'followup_count', 'overall_response']);
        });
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['province', 'gender', 'age_range']);
        });
        Schema::dropIfExists('products');
    }
};
