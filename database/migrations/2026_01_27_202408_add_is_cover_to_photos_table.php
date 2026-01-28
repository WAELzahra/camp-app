<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('photos', function (Blueprint $table) {
            // Add is_cover column with default false
            $table->boolean('is_cover')->default(false)->after('album_id');
            
            // Add order column for ordering photos within album
            $table->integer('order')->default(0)->after('is_cover');
        });
    }

    public function down()
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropColumn(['is_cover', 'order']);
        });
    }
};