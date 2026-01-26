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
        Schema::table('profiles', function (Blueprint $table) {
            // Remove adresse and immatricule columns
            $table->dropColumn(['adresse', 'immatricule']);
        });

        Schema::table('profile_guides', function (Blueprint $table) {
            // Add cin field for guides
            $table->string('cin', 255)->nullable()->after('profile_id');
        });

        Schema::table('profile_fournisseurs', function (Blueprint $table) {
            // Add cin field for fournisseurs
            $table->string('cin', 255)->nullable()->after('profile_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // Restore dropped columns
            $table->string('adresse', 255)->nullable();
            $table->string('immatricule', 255)->nullable();
        });

        Schema::table('profile_guides', function (Blueprint $table) {
            $table->dropColumn('cin');
        });

        Schema::table('profile_fournisseurs', function (Blueprint $table) {
            $table->dropColumn('cin');
        });
    }
};