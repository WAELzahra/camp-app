<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop the ENUM constraint by changing to VARCHAR(50)
        DB::statement("ALTER TABLE reports MODIFY COLUMN report_type VARCHAR(50) NOT NULL DEFAULT 'other'");
    }

    public function down(): void
    {
        // Clamp any values that don't fit back into the ENUM before reverting
        DB::statement("UPDATE reports SET report_type = 'other' WHERE report_type NOT IN ('bug','suspicious_user','safety_concern','other')");
        DB::statement("ALTER TABLE reports MODIFY COLUMN report_type ENUM('bug','suspicious_user','safety_concern','other') NOT NULL DEFAULT 'other'");
    }
};
