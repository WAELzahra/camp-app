<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires re-specifying all enum values when altering
        DB::statement("ALTER TABLE favorites MODIFY COLUMN favoritable_type ENUM('profile','centre','zone','equipment','annonce') NOT NULL");
    }

    public function down(): void
    {
        // Remove rows with 'annonce' type first to avoid constraint errors
        DB::table('favorites')->where('favoritable_type', 'annonce')->delete();
        DB::statement("ALTER TABLE favorites MODIFY COLUMN favoritable_type ENUM('profile','centre','zone','equipment') NOT NULL");
    }
};
