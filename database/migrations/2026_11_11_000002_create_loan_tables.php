<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('whatsapp_number', 20)->index();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamp('data_consent_at')->nullable();
            $table->timestamp('whatsapp_consent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('new')->index();
            $table->string('cancel_reason', 20)->nullable();
            $table->text('cancel_note')->nullable();
            $table->text('notes')->nullable();
            $table->string('source', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('event_name')->nullable();
            $table->date('starts_on');
            $table->date('due_on');
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('returned_at')->nullable();
            $table->foreignId('returned_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('return_notes')->nullable();
            $table->foreignId('override_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->index(['sales_id', 'status']);
        });

        Schema::create('loan_return_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->string('component');
            $table->boolean('is_complete');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('unit_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('stage', 20);
            $table->string('file_path');
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 60);
            $table->string('from')->nullable();
            $table->string('to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        // Append-only audit log: block UPDATE and DELETE at the database level too.
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION activity_logs_append_only() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'activity_logs is append-only';
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER activity_logs_no_update_delete
                BEFORE UPDATE OR DELETE ON activity_logs
                FOR EACH ROW EXECUTE FUNCTION activity_logs_append_only();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP TRIGGER IF EXISTS activity_logs_no_update_delete ON activity_logs; DROP FUNCTION IF EXISTS activity_logs_append_only();');
        }

        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('unit_photos');
        Schema::dropIfExists('loan_return_checks');
        Schema::dropIfExists('loans');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('customers');
    }
};
