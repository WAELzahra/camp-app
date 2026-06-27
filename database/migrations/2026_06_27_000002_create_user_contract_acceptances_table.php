<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_contract_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('legal_document_id')
                  ->constrained('legal_documents')
                  ->onDelete('cascade');

            $table->timestamp('accepted_at');
            $table->string('ip_address', 45);       // IPv4 or IPv6
            $table->string('user_agent', 512);
            $table->enum('acceptance_method', ['registration', 'modal', 'api']);

            $table->timestamps();

            // A user can only accept each document version once.
            $table->unique(['user_id', 'legal_document_id']);

            // Fast lookups: "which users accepted doc X?", "all acceptances after date Y"
            $table->index('legal_document_id');
            $table->index('accepted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_contract_acceptances');
    }
};
