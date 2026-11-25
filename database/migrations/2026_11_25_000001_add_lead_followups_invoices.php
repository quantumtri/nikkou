<?php

use App\Models\Sequence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('price')->default(0)->after('category'); // Rupiah
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique();
            $table->string('path', 10)->default('trial'); // trial | direct
        });

        // Accessories travel with the lead: trial carry-list, or invoice lines for a direct purchase.
        Schema::create('lead_accessories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->unique(['lead_id', 'product_id']);
        });

        Schema::create('lead_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('followed_up_on');
            $table->string('channel', 12);
            $table->string('response', 10);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['lead_id', 'followed_up_on']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('lead_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 12)->default('unpaid')->index();
            $table->date('issued_on');
            $table->date('paid_on')->nullable();
            $table->unsignedBigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // snapshot, so later catalog edits never rewrite an invoice
            $table->unsignedBigInteger('unit_price');
            $table->unsignedSmallInteger('quantity');
        });

        // Number the leads that already exist, oldest first.
        foreach (DB::table('leads')->orderBy('id')->get(['id', 'created_at']) as $lead) {
            $year = $lead->created_at ? substr((string) $lead->created_at, 0, 4) : date('Y');
            $n = Sequence::next("lead.$year");
            DB::table('leads')->where('id', $lead->id)->update(['code' => sprintf('LD-%s-%04d', $year, $n)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('lead_followups');
        Schema::dropIfExists('lead_accessories');
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['code', 'path']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
