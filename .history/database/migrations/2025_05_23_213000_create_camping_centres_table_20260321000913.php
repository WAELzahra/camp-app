<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
       Schema::create('camping_centres', function (Blueprint $table) {
    $table->id();
    $table->string('nom');
    $table->string('type')->default('centre'); // centre / hors_centre
    $table->text('description')->nullable();
    $table->string('adresse')->nullable();
    $table->decimal('lat', 10, 7)->nullable();
    $table->decimal('lng', 10, 7)->nullable();
    $table->string('image')->nullable();

    $table->tinyInteger('status')->default(0); // 0 = privé, 1 = public
    $table->string('validation_status')->default('pending'); // pending / approved / rejected

    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('profile_centre_id')->nullable()->constrained('profile_centres')->nullOnDelete();

    $table->timestamps();
});

    }

    public function down(): void {
        Schema::dropIfExists('camping_centres');
    }
};
