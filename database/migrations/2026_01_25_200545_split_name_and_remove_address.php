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
        // Add address to specific profile tables
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable()->after('profile_id');
        });

        Schema::table('profile_fournisseurs', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable()->after('profile_id');
        });

        Schema::table('profile_guides', function (Blueprint $table) {
            $table->string('adresse', 255)->nullable()->after('profile_id');
        });

        // Split name into first_name and last_name in users table
        Schema::table('users', function (Blueprint $table) {
            // Add new columns
            $table->string('first_name', 255)->nullable()->after('name');
            $table->string('last_name', 255)->nullable()->after('first_name');
            
            // Remove old adresse column from users table
            $table->dropColumn('adresse');
        });

        // Update existing data (migrate name to first_name and last_name)
        $this->migrateUserNames();

        // Make first_name and last_name not nullable
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 255)->nullable(false)->change();
            $table->string('last_name', 255)->nullable(false)->change();
        });

        // Drop the old name column
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore name column
        Schema::table('users', function (Blueprint $table) {
            $table->string('name', 255)->nullable()->after('email');
        });

        // Migrate data back (merge first_name and last_name)
        $this->rollbackUserNames();

        // Drop new columns
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name']);
            // Restore adresse column
            $table->string('adresse', 255)->nullable()->after('phone_number');
        });

        // Remove address from profile tables
        Schema::table('profile_centres', function (Blueprint $table) {
            $table->dropColumn('adresse');
        });

        Schema::table('profile_fournisseurs', function (Blueprint $table) {
            $table->dropColumn('adresse');
        });

        Schema::table('profile_guides', function (Blueprint $table) {
            $table->dropColumn('adresse');
        });
    }

    /**
     * Migrate existing name data to first_name and last_name
     */
    private function migrateUserNames(): void
    {
        $users = \DB::table('users')->get();
        
        foreach ($users as $user) {
            $nameParts = explode(' ', $user->name, 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            
            \DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
        }
    }

    /**
     * Rollback name data
     */
    private function rollbackUserNames(): void
    {
        $users = \DB::table('users')->get(['id', 'first_name', 'last_name']);
        
        foreach ($users as $user) {
            $fullName = trim($user->first_name . ' ' . $user->last_name);
            
            \DB::table('users')
                ->where('id', $user->id)
                ->update(['name' => $fullName]);
        }
    }
};