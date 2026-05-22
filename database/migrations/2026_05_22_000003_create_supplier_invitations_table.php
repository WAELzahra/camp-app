<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organizer_id')->constrained('users')->onDelete('cascade');
            $table->string('email');
            $table->enum('status', ['pending', 'registered', 'expired', 'cancelled'])->default('pending');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index('organizer_id');
            $table->index('email');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invitations');
    }
};
