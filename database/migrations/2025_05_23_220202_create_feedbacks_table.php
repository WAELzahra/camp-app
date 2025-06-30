<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();

            // Utilisateur qui fait le feedback (campeur)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Utilisateur cible (ex groupe de camping)
            $table->foreignId('target_id')->constrained('users')->onDelete('cascade');

            // Optionnel : l'événement concerné
            $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('cascade');

            // Optionnel : zone de camping concernée
            $table->foreignId('zone_id')->nullable()->constrained('camping_zones')->onDelete('cascade');

            // Commentaire texte
            $table->string('contenu')->nullable();

            // Réponse possible (du groupe)
            $table->string('response')->nullable();

            // Note entre 1 et 5 étoiles
            $table->unsignedTinyInteger('note')->nullable();

            // Type de feedback (ex: groupe, evenement, materielle, etc)
            $table->string('type')->default('groupe');

            // Statut de modération (pending = en attente, approved = validé, rejected = refusé)
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');

            $table->timestamps();

            // Index pour accélérer les recherches par cible
            $table->index('target_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('feedbacks');
    }
};
