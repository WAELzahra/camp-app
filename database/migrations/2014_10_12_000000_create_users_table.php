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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // Infos personnelles
            $table->string('phone_number')->nullable();
            $table->string('adresse')->nullable();
            $table->string('ville')->nullable();
            $table->date('date_naissance')->nullable();
            $table->string('sexe')->nullable();
            $table->string('langue')->nullable();

            // Gestion du compte
            $table->foreignId('role_id')->constrained('roles');
            $table->boolean('is_active')->default(0);
            $table->boolean('first_login')->default(true);
            $table->integer('nombre_signalement')->default(0);
            $table->timestamp('last_login_at')->nullable();
            $table->string('avatar')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
