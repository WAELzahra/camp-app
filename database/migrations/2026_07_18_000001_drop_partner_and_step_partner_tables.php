<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reverts the "manual Partner directory" design: a Programme step should
 * reference an existing, already-published listing (Event / CampingCentre /
 * Materielle) owned by an existing platform actor, not a freestanding
 * Partner record re-entered by the admin. See programme_items (next
 * migration) for the replacement. No production data exists for any of
 * these tables yet (pre-launch), so a clean drop-and-recreate is safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('programme_reservation_shares');
        Schema::dropIfExists('programme_step_partners');
        Schema::dropIfExists('programme_steps');
        Schema::dropIfExists('partners');
        Schema::dropIfExists('partner_types');
    }

    public function down(): void
    {
        Schema::create('partner_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label', 100);
            $table->string('icon', 50)->nullable();
            $table->boolean('requires_platform_account')->default(true);
            $table->timestamps();
        });

        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_type_id')->constrained('partner_types')->onDelete('restrict');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name', 255);
            $table->string('contact_email', 255)->nullable();
            $table->string('contact_phone', 50)->nullable();
            $table->decimal('default_commission_rate', 5, 2)->default(10);
            $table->enum('status', ['active', 'pending', 'suspended'])->default('active');
            $table->timestamps();
        });

        Schema::create('programme_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained('programmes')->onDelete('cascade');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->unsignedInteger('day_offset')->default(0);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location_label', 255)->nullable();
            $table->decimal('location_lat', 10, 7)->nullable();
            $table->decimal('location_lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('programme_step_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_step_id')->constrained('programme_steps')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('partners')->onDelete('restrict');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('programme_reservation_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_reservation_id')->constrained('programme_reservations')->onDelete('cascade');
            $table->foreignId('partner_id')->constrained('partners')->onDelete('restrict');
            $table->foreignId('programme_step_partner_id')->nullable()->constrained('programme_step_partners')->onDelete('set null');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('net_amount', 10, 2);
            $table->boolean('partner_credited')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }
};
