<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Update reservations_centres table
        DB::statement("ALTER TABLE reservations_centres MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'canceled', 'modified') NOT NULL DEFAULT 'pending'");
        
        // Update reservation_service_items table if it has the same ENUM
        DB::statement("ALTER TABLE reservation_service_items MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'canceled', 'modified') NOT NULL DEFAULT 'pending'");
    }

    public function down()
    {
        // Revert back (remove 'modified')
        DB::statement("ALTER TABLE reservations_centres MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'canceled') NOT NULL DEFAULT 'pending'");
        DB::statement("ALTER TABLE reservation_service_items MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'canceled') NOT NULL DEFAULT 'pending'");
    }
};