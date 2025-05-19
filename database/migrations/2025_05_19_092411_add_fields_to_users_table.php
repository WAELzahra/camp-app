<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('ville')->nullable()->after('phone_number');
            $table->date('date_naissance')->nullable()->after('ville');
            $table->string('sexe')->nullable()->after('date_naissance');
            $table->string('langue')->nullable()->after('sexe');
            $table->boolean('first_login')->default(true)->after('is_active');  // correction ici
            $table->integer('nombre_signalement')->default(0)->after('first_login');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'ville',
                'date_naissance',
                'sexe',
                'langue',
                'first_login',
                'nombre_signalement'
            ]);
        });
    }
};
