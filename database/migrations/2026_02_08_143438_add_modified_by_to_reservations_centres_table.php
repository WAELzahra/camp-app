<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->enum('last_modified_by', ['center', 'user'])->nullable();
            $table->timestamp('last_modified_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('reservations_centres', function (Blueprint $table) {
            $table->dropColumn(['last_modified_by', 'last_modified_at']);
        });
    }
};