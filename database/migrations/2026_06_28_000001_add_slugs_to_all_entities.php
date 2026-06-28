<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // ── Add slug columns ──────────────────────────────────────────────────
        Schema::table('camping_centres', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nom');
        });

        Schema::table('boutiques', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nom_boutique');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
        });

        Schema::table('camping_zones', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nom');
        });

        Schema::table('profile_groupes', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nom_groupe');
        });

        Schema::table('materielles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('nom');
        });

        // ── Backfill existing records ─────────────────────────────────────────
        $this->backfill('camping_centres', 'nom');
        $this->backfill('boutiques', 'nom_boutique');
        $this->backfill('events', 'title');
        $this->backfill('camping_zones', 'nom');
        $this->backfill('profile_groupes', 'nom_groupe');
        $this->backfill('materielles', 'nom');
    }

    public function down(): void
    {
        foreach (['camping_centres', 'boutiques', 'events', 'camping_zones', 'profile_groupes', 'materielles'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('slug');
            });
        }
    }

    private function backfill(string $table, string $nameColumn): void
    {
        $rows = \DB::table($table)->select('id', $nameColumn)->get();

        foreach ($rows as $row) {
            $base = Str::slug($row->$nameColumn) ?: 'item';
            $slug = $base;
            $i    = 2;

            // Ensure uniqueness within the table
            while (\DB::table($table)->where('slug', $slug)->exists()) {
                $slug = $base . '-' . $i++;
            }

            \DB::table($table)->where('id', $row->id)->update(['slug' => $slug]);
        }
    }
};
