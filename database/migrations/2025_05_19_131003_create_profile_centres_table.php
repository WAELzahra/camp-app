<?php

// database/migrations/2025_05_19_130714_create_profile_centres_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('profile_centres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profile_id')->constrained('profiles')->onDelete('cascade');
            $table->integer('capacite')->nullable();
            $table->text('services_offerts')->nullable();
            $table->string('document_legal')->nullable();
            $table->boolean('disponibilite')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('profile_centres');
    }
};
