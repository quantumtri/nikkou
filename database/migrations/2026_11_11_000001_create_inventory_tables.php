<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            // Default completeness checklist copied to each new unit of this type.
            $table->json('default_components')->nullable();
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->foreignId('unit_type_id')->constrained()->restrictOnDelete();
            $table->date('received_on');
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->string('forwarder')->nullable();
            $table->string('location')->default('Gudang Surabaya');
            $table->string('status', 20)->default('available')->index();
            $table->text('initial_notes')->nullable();
            $table->timestamp('label_printed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('unit_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_present')->default(true);
            $table->timestamps();
        });

        Schema::create('unit_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_documents');
        Schema::dropIfExists('unit_components');
        Schema::dropIfExists('units');
        Schema::dropIfExists('unit_types');
    }
};
