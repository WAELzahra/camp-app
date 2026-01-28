<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('albums', function (Blueprint $table) {
            // Add user_id column if it doesn't exist
            if (!Schema::hasColumn('albums', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id');
            }
            
            // Add foreign key constraint
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade')
                  ->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::table('albums', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['user_id']);
            
            // Then drop the column
            $table->dropColumn('user_id');
        });
    }
};