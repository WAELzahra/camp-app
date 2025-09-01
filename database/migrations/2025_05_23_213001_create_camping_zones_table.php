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
        Schema::create('camping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('type_activitee');
            $table->boolean('is_public')->default(true);
            $table->text('description')->nullable();
            $table->string('adresse');
            $table->enum('danger_level', ['low', 'moderate', 'high', 'extreme'])->default('low');
            $table->boolean('status')->default(false); // true = ouverte, false = fermée
            
            // Localisation GPS point
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Centre lié (inscrit ou non)
            $table->foreignId('centre_id')->nullable()->constrained('camping_centres')->nullOnDelete();

            // Champs supplémentaires
            $table->string('region')->nullable();
            $table->string('commune')->nullable();
            $table->enum('access_type', ['road', 'trail', 'boat', 'mixed'])->nullable();

            $table->integer('max_capacity')->nullable();
            $table->string('opening_season')->nullable();

            $table->json('facilities')->nullable(); // équipements disponibles (toilettes, eau, etc.)
            $table->json('activities')->nullable(); // activités possibles (randonnée, pêche...)

            $table->boolean('is_protected_area')->default(false); // zone protégée par l’état

            // Fermeture / interdiction
            $table->boolean('is_closed')->default(false); // fermeture temporaire ou définitive
            $table->string('closure_reason')->nullable(); // raison de fermeture (décision état, météo, etc.)
            $table->date('closure_start')->nullable();
            $table->date('closure_end')->nullable();

            $table->json('emergency_contacts')->nullable(); // contacts d’urgence (police, pompiers, etc.)

            $table->integer('map_zoom_level')->default(14); // zoom par défaut sur la carte
            $table->json('polygon_coordinates')->nullable(); // coordonnées polygone GeoJSON ou similaire

            $table->string('weather_station_id')->nullable(); // pour récupération météo externe
            $table->timestamp('last_weather_update')->nullable();

            // Image et source
            $table->string('image')->nullable();
            $table->string('source')->default('interne');

            // Utilisateur ayant ajouté la zone
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camping_zones');
    }
};
