<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programme_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->enum('type', ['rule', 'condition']);
            $table->text('content');
            $table->unsignedInteger('sort_order')->default(0);

            $table->index('programme_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programme_rules');
    }
};
