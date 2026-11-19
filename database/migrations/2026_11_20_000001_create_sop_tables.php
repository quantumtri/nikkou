<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sops', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('title');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('draft')->index();
            // Added after sop_versions exists (circular reference).
            $table->unsignedBigInteger('active_version_id')->nullable();
            $table->date('review_on')->nullable();
            $table->timestamps();
        });

        Schema::create('sop_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('status', 12)->default('draft')->index();
            $table->text('change_summary')->nullable();
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['sop_id', 'number']);
        });

        Schema::table('sops', function (Blueprint $table) {
            $table->foreign('active_version_id')->references('id')->on('sop_versions')->nullOnDelete();
        });

        Schema::create('sop_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('position')->default(1);
        });

        Schema::create('sop_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 30);
            $table->unsignedSmallInteger('number')->nullable();
            $table->string('type', 12);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('sop_role_id')->nullable()->constrained('sop_roles')->nullOnDelete();
            $table->string('deadline')->nullable();
            $table->string('related_route')->nullable();
            $table->unsignedSmallInteger('position')->default(1);

            $table->unique(['sop_version_id', 'key']);
        });

        Schema::create('sop_edges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_step_id')->constrained('sop_steps')->cascadeOnDelete();
            $table->foreignId('to_step_id')->constrained('sop_steps')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('type', 10)->default('normal');
        });

        Schema::create('sop_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sop_step_id')->nullable()->constrained('sop_steps')->nullOnDelete();
            $table->string('name');
            $table->string('type', 12);
            $table->string('route_or_file');
        });

        Schema::create('sop_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Whole role (admin, sales, warehouse) instead of one person.
            $table->string('role', 20)->nullable();
        });

        Schema::create('sop_acknowledgements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sop_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['sop_version_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sop_acknowledgements');
        Schema::dropIfExists('sop_assignments');
        Schema::dropIfExists('sop_forms');
        Schema::dropIfExists('sop_edges');
        Schema::dropIfExists('sop_steps');
        Schema::dropIfExists('sop_roles');
        Schema::table('sops', fn (Blueprint $table) => $table->dropForeign(['active_version_id']));
        Schema::dropIfExists('sop_versions');
        Schema::dropIfExists('sops');
    }
};
