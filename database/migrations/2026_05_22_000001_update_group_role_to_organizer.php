<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the role display name from 'groupe' to 'organizer'
        // role_id = 2 stays unchanged for backward compatibility
        DB::table('roles')
            ->where('id', 2)
            ->update(['name' => 'organizer']);
    }

    public function down(): void
    {
        DB::table('roles')
            ->where('id', 2)
            ->update(['name' => 'groupe']);
    }
};
