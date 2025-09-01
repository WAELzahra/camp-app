<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_group_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('chat_group_id');
            $table->unsignedBigInteger('user_id');

            $table->foreign('chat_group_id')->references('id')->on('chat_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('chat_group_users', function (Blueprint $table) {
            $table->dropForeign(['chat_group_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::dropIfExists('chat_group_users');
    }
};
