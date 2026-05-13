<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materielles', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('nom');
            $table->json('trip_type_tags')->nullable()->after('description');
            $table->decimal('weight_kg', 5, 2)->nullable()->after('trip_type_tags');
            $table->enum('condition', ['new', 'like_new', 'good', 'fair'])->default('new')->after('weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('materielles', function (Blueprint $table) {
            $table->dropColumn(['brand', 'trip_type_tags', 'weight_kg', 'condition']);
        });
    }
};
