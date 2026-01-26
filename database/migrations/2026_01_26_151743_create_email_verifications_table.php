<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('email');
            $table->string('code', 6)->nullable(); 
            $table->string('token')->nullable(); 
            $table->timestamp('expires_at');
            $table->integer('attempts')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->enum('method', ['code', 'link'])->default('code');
            $table->timestamps();
            
            $table->index(['user_id', 'email']);
            $table->index('code');
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_verifications');
    }
};