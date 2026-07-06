<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'kyc_status')) {
                $table->enum('kyc_status', ['not_required', 'pending', 'verified', 'rejected'])
                    ->default('not_required')->after('is_active')->index();
            }
            if (!Schema::hasColumn('users', 'kyc_submitted_at')) {
                $table->timestamp('kyc_submitted_at')->nullable()->after('kyc_status');
            }
            if (!Schema::hasColumn('users', 'kyc_verified_at')) {
                $table->timestamp('kyc_verified_at')->nullable()->after('kyc_submitted_at');
            }
            if (!Schema::hasColumn('users', 'kyc_rejected_at')) {
                $table->timestamp('kyc_rejected_at')->nullable()->after('kyc_verified_at');
            }
            if (!Schema::hasColumn('users', 'kyc_document_type')) {
                $table->string('kyc_document_type', 50)->nullable()->after('kyc_rejected_at');
            }
            if (!Schema::hasColumn('users', 'kyc_document_path')) {
                $table->string('kyc_document_path', 500)->nullable()->after('kyc_document_type');
            }
            if (!Schema::hasColumn('users', 'kyc_notes')) {
                $table->text('kyc_notes')->nullable()->after('kyc_document_path');
            }
            if (!Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', ['active', 'pending_kyc', 'suspended'])
                    ->default('active')->after('kyc_notes')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'kyc_status', 'kyc_submitted_at', 'kyc_verified_at', 'kyc_rejected_at',
                'kyc_document_type', 'kyc_document_path', 'kyc_notes', 'account_status',
            ]);
        });
    }
};
