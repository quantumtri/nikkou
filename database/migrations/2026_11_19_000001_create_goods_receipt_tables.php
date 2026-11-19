<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free counters guarded by a row lock: receipt numbers per month and unit codes.
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name')->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        Schema::table('units', function (Blueprint $table) {
            $table->string('shelf', 40)->nullable()->after('location');
        });

        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 20)->unique();
            $table->date('received_on');
            $table->string('po_number')->nullable();
            $table->string('supplier')->nullable();
            $table->string('forwarder')->nullable();
            $table->string('tracking_number')->nullable();
            $table->unsignedSmallInteger('packages_expected')->nullable();
            $table->unsignedSmallInteger('packages_received')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedSmallInteger('current_step')->default(1);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('summary_sent_at')->nullable();
            // Filled in later from the paper form: the photo of the paper sheet is then required.
            $table->boolean('from_paper')->default(false);
            $table->timestamps();
        });

        Schema::create('goods_receipt_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->boolean('passed');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['goods_receipt_id', 'code']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(1);
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('unit_code', 40)->nullable()->unique();
            $table->string('serial_number')->nullable();
            // Component checklist at intake: [{"name": "Selang", "present": true}, ...]
            $table->json('components')->nullable();
            $table->boolean('is_complete')->nullable();
            $table->boolean('physical_ok')->nullable();
            $table->boolean('powers_on')->nullable();
            $table->boolean('outputs')->nullable();
            $table->boolean('no_leak')->nullable();
            $table->boolean('no_abnormal')->nullable();
            $table->string('result', 10)->nullable();
            $table->text('fail_reason')->nullable();
            $table->boolean('is_retest')->default(false);
            $table->foreignId('tester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('shelf', 40)->nullable();
            $table->timestamp('label_read_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->text('description');
            $table->string('action', 30)->nullable();
            $table->date('due_on')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 12)->default('open')->index();
            // Findings that stop the unit check (step 4) until closed or waived by the owner.
            $table->boolean('is_blocking')->default(false);
            $table->foreignId('created_by_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('waiver_reason')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('goods_receipt_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('goods_receipt_finding_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('document_type', 20)->nullable();
            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Messages queued for the admins on channels that are not wired up yet (WhatsApp).
        Schema::create('admin_message_queue', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20);
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 12)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_message_queue');
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('goods_receipt_files');
        Schema::dropIfExists('goods_receipt_findings');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipt_checks');
        Schema::dropIfExists('goods_receipts');
        Schema::table('units', fn (Blueprint $table) => $table->dropColumn('shelf'));
        Schema::dropIfExists('sequences');
    }
};
