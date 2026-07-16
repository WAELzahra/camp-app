<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE cancellation_policies
            MODIFY COLUMN type ENUM('centre', 'materiel', 'event', 'programme') NOT NULL"
        );

        DB::statement("ALTER TABLE promo_codes
            MODIFY COLUMN applicable_to ENUM('all', 'centre', 'materiel', 'event', 'programme') NOT NULL DEFAULT 'all'"
        );
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE cancellation_policies
            MODIFY COLUMN type ENUM('centre', 'materiel', 'event') NOT NULL"
        );

        DB::statement("ALTER TABLE promo_codes
            MODIFY COLUMN applicable_to ENUM('all', 'centre', 'materiel', 'event') NOT NULL DEFAULT 'all'"
        );
    }
};
