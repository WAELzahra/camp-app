<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('popups', function (Blueprint $table) {
            $table->enum('popup_kind', ['engagement', 'welcome'])->default('engagement')->after('type');
            $table->json('target_roles')->nullable()->after('popup_kind'); // null = all roles
            $table->string('icon')->nullable()->after('target_roles');     // react-icons name e.g. "FiMapPin"
            $table->string('cta_label')->nullable()->after('icon');
            $table->string('cta_url')->nullable()->after('cta_label');
        });
    }

    public function down(): void
    {
        Schema::table('popups', function (Blueprint $table) {
            $table->dropColumn(['popup_kind', 'target_roles', 'icon', 'cta_label', 'cta_url']);
        });
    }
};
