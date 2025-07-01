<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('pending', 'scheduled', 'finished', 'canceled', 'postponed', 'full')");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE events MODIFY COLUMN status ENUM('scheduled', 'finished', 'canceled', 'postponed', 'full')");
    }
};
