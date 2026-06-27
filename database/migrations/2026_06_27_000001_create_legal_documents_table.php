<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['cgu', 'cgv', 'mentions_legales', 'confidentialite']);
            $table->string('version', 20);        // e.g. "1.0", "2.1"
            $table->date('effective_date');
            $table->longText('content_fr');
            $table->longText('content_en');
            $table->longText('content_ar');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            // Only one active version per type at a time (enforced in service, not DB,
            // because deactivation + insertion happen in a transaction).
            $table->unique(['type', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
