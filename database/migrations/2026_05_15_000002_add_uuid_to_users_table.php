<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a stable public UUID to every user row.
 *
 * The numeric `id` is kept for internal FK references and query performance.
 * The `uuid` is what API Resources expose to clients — it cannot be used to
 * enumerate other users because there is no sequential correlation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // After the primary key, before email
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        // Back-fill existing rows — run in a single UPDATE for speed,
        // then loop only over any that are still NULL (shouldn't happen).
        DB::table('users')->orderBy('id')->chunk(500, function ($users) {
            foreach ($users as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->whereNull('uuid')
                    ->update(['uuid' => (string) Str::uuid()]);
            }
        });

        // Now make the column non-nullable
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};
