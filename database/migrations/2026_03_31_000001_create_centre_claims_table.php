<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('centre_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->constrained('camping_centres')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('message');
            $table->string('proof_document')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Un utilisateur ne peut avoir qu'une seule demande en cours par centre
            $table->unique(['centre_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('centre_claims');
    }
};
