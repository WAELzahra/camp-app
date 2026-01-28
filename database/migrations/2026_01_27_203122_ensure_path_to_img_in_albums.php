<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Check if path_to_img column exists, add it if not
        if (!Schema::hasColumn('albums', 'path_to_img')) {
            Schema::table('albums', function (Blueprint $table) {
                $table->string('path_to_img', 255)->nullable()->after('user_id');
            });
        }
    }

    public function down()
    {
        // Don't drop column in down() to avoid data loss
        // You can remove this or adjust as needed
    }
};