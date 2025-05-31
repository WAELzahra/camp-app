<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('badges', function (Blueprint $table) {
            $table->id(); // Auto-incrementing primary key
            $table->foreignId('guide_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade');
            $table->date('creation_date');
            $table->string('titre');
            $table->string('decription');
            $table->enum('type', ['certification', 'reputation']);
            $table->string('icon');
            $table->timestamps();

            // Unique constraint instead of composite primary key
            $table->unique(['provider_id', 'guide_id', 'creation_date'], 'badge_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badges');
    }
};

