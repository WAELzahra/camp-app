<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reservation_service_items', function (Blueprint $table) {
            $table->enum('rejected_by', ['center', 'user'])->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('reservation_service_items', function (Blueprint $table) {
            $table->dropColumn(['rejected_by', 'rejection_reason', 'rejected_at']);
        });
    }
};