<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChatGroupMessagesTable extends Migration
{
    public function up()
    {
        Schema::create('chat_group_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_group_id');
            $table->unsignedBigInteger('sender_id'); // ID de l'utilisateur (campeur)
            $table->text('message');
            $table->timestamps();

            $table->foreign('chat_group_id')->references('id')->on('chat_groups')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('chat_group_messages');
    }
}
