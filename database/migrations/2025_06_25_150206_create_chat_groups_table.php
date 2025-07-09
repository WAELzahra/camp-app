<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_groups', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('group_id'); // Groupe de camping
            $table->string('name'); // Nom du groupe de chat
            $table->string('invitation_token')->unique(); // Lien d'invitation unique

            $table->boolean('is_archived')->default(false); // ✅ Archivé ou non

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_groups');
    }
};
