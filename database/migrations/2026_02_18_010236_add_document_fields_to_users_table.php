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
        Schema::table('users', function (Blueprint $table) {
            // Documents légaux pour tous les utilisateurs
            $table->string('cin')->nullable()->after('langue');
            $table->string('cin_recto')->nullable()->after('cin');
            $table->string('cin_verso')->nullable()->after('cin_recto');
            
            // Documents spécifiques
            $table->string('certificat')->nullable()->after('cin_verso'); // Pour guides
            $table->string('patente')->nullable()->after('certificat'); // Pour groupes
            $table->string('registre_commerce')->nullable()->after('patente'); // Pour fournisseurs
            $table->string('licence')->nullable()->after('registre_commerce'); // Pour centres
            
            // Statut de vérification des documents
            $table->enum('documents_status', ['pending', 'verified', 'rejected'])->default('pending')->after('licence');
            $table->timestamp('documents_verified_at')->nullable()->after('documents_status');
            $table->foreignId('documents_verified_by')->nullable()->constrained('users')->after('documents_verified_at');
            
            // Nouveaux champs pour les profils spécifiques
            $table->string('siret')->nullable(); // Pour fournisseurs
            $table->string('tva_number')->nullable(); // Pour fournisseurs
            $table->string('company_name')->nullable(); // Pour fournisseurs/centres
            $table->string('legal_representative')->nullable(); // Représentant légal
            $table->string('representative_cin')->nullable(); // CIN du représentant
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cin',
                'cin_recto',
                'cin_verso',
                'certificat',
                'patente',
                'registre_commerce',
                'licence',
                'documents_status',
                'documents_verified_at',
                'documents_verified_by',
                'siret',
                'tva_number',
                'company_name',
                'legal_representative',
                'representative_cin',
            ]);
        });
    }
};