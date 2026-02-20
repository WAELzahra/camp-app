<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profile_fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->onDelete('cascade');
            $table->string('intervale_prix')->nullable();
            $table->string('product_category')->nullable();
            
            // Documents du fournisseur
            $table->string('cin_commercant_path')->nullable(); // CIN ou carte commerçant
            $table->string('cin_commercant_filename')->nullable();
            $table->string('registre_commerce_path')->nullable(); // Registre de commerce
            $table->string('registre_commerce_filename')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profile_fournisseurs');
    }
};