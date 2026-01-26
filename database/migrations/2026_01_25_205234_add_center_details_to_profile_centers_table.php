<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            // Add new columns for better center management
            $table->string('name')->nullable()->after('profile_id');
            $table->decimal('price_per_night', 10, 2)->nullable()->after('capacite');
            $table->string('category')->nullable()->after('price_per_night'); // Budget, Standard, Premium, etc.
            $table->decimal('latitude', 10, 8)->nullable()->after('adresse');
            $table->decimal('longitude', 10, 8)->nullable()->after('latitude');
            $table->string('contact_email')->nullable()->after('longitude');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('manager_name')->nullable()->after('contact_phone');
            $table->date('established_date')->nullable()->after('manager_name');
            
            // Rename existing columns for clarity
            $table->renameColumn('document_legal', 'legal_document');
            $table->renameColumn('id_annonce', 'ad_id');
            $table->renameColumn('id_album_photo', 'photo_album_id');
            
            // Change services_offerts to be a general description field
            $table->text('additional_services_description')->nullable()->after('services_offerts');
        });
    }

    public function down(): void
    {
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'price_per_night',
                'category',
                'latitude',
                'longitude',
                'contact_email',
                'contact_phone',
                'manager_name',
                'established_date',
                'additional_services_description'
            ]);
            
            $table->renameColumn('legal_document', 'document_legal');
            $table->renameColumn('ad_id', 'id_annonce');
            $table->renameColumn('photo_album_id', 'id_album_photo');
        });
    }
};