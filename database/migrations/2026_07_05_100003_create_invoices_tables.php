<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->string('invoice_number', 20)->unique(); // TC-YYYY-XXXXXX
                $table->enum('invoice_type', ['sale', 'commission']);
                // Reservations live in 3 tables — store id + type rather than a hard FK.
                $table->unsignedBigInteger('reservation_id');
                $table->string('reservation_type', 30)->default('centre'); // centre|event|materielle
                $table->string('issuer_entity')->default('[FRIEND_FULL_NAME]');
                $table->string('issuer_fiscal_id')->default('[FISCAL_ID]');
                $table->string('client_name');
                $table->string('client_fiscal_id')->nullable();
                $table->decimal('amount_ht', 10, 2);
                $table->decimal('tva_rate', 5, 2)->default(19.00);
                $table->decimal('tva_amount', 10, 2);
                $table->decimal('timbre_fiscal', 5, 2)->default(0.600);
                $table->decimal('amount_ttc', 10, 2);
                $table->string('payment_method', 50)->nullable();
                $table->timestamp('issued_at');
                $table->string('pdf_path', 500)->nullable();
                // Invoices are never deleted — only voided (Task 3D).
                $table->timestamp('voided_at')->nullable();
                $table->unsignedBigInteger('voided_by')->nullable();
                $table->string('void_reason')->nullable();
                $table->timestamps();

                $table->index(['reservation_type', 'reservation_id']);
            });
        }

        // Locked counter for gap-free sequential numbering (one row per year).
        if (!Schema::hasTable('invoice_sequences')) {
            Schema::create('invoice_sequences', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('year')->unique();
                $table->unsignedBigInteger('last_number')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('invoice_audit_logs')) {
            Schema::create('invoice_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('invoice_id')->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 30); // generated | downloaded | voided
                $table->string('details')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_audit_logs');
        Schema::dropIfExists('invoice_sequences');
        Schema::dropIfExists('invoices');
    }
};
