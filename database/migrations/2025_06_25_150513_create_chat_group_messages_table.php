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
Schema::create('chat_group_typing_statuses', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('chat_group_id');
    $table->unsignedBigInteger('user_id');
    $table->boolean('is_typing')->default(false);
    $table->timestamp('updated_at')->nullable();

    $table->foreign('chat_group_id')->references('id')->on('chat_groups')->onDelete('cascade');
});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_group_messages');
    }
};
   