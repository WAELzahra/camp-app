<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materielles_categories', function (Blueprint $table) {
            $table->json('trip_contexts')->nullable()->after('description');
            $table->string('icon')->nullable()->after('trip_contexts');
            $table->boolean('is_safety_critical')->default(false)->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('materielles_categories', function (Blueprint $table) {
            $table->dropColumn(['trip_contexts', 'icon', 'is_safety_critical']);
        });
    }
};
