<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Set when the customer asks to stop; no automatic message is sent after this.
            $table->timestamp('whatsapp_opted_out_at')->nullable()->after('whatsapp_consent_at');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('serious_complaint_at')->nullable();
        });

        Schema::create('followup_templates', function (Blueprint $table) {
            $table->id();
            $table->string('group', 20);
            $table->string('step_key', 40);
            $table->string('title');
            $table->text('body');
            // Customer-facing wording must be reviewed and approved before it can be switched on (spec sections 7 and 10).
            $table->boolean('requires_review')->default(true);
            $table->boolean('is_active')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['group', 'step_key']);
        });

        Schema::create('followup_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('template_id')->constrained('followup_templates')->restrictOnDelete();
            $table->string('step_key', 40);
            $table->unsignedSmallInteger('sequence')->default(1);
            $table->timestamp('send_at')->index();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->string('waha_message_id')->nullable();
            $table->text('text')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'step_key', 'sequence']);
        });

        Schema::create('experience_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 3);
            $table->text('body');
            // WAHA message id for inbound, "out:schedule:<id>" for outbound: makes redelivery harmless.
            $table->string('message_key')->nullable()->unique();
            $table->boolean('is_serious')->default(false);
            $table->boolean('handled')->default(false);
            $table->text('reply_text')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        Schema::create('safety_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_keywords');
        Schema::dropIfExists('experience_messages');
        Schema::dropIfExists('followup_schedules');
        Schema::dropIfExists('followup_templates');
        Schema::table('leads', fn (Blueprint $table) => $table->dropColumn('serious_complaint_at'));
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('whatsapp_opted_out_at'));
    }
};
