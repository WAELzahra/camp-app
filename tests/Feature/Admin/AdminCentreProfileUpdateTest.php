<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;
use App\Models\Profile;
use App\Models\ProfileCentre;
use App\Models\CampingCentre;
use App\Models\ProfileCenterService;
use App\Models\ProfileCenterEquipment;

/**
 * Regression test for the admin "Edit Centre" modal's Tarifs & Services tab
 * (CampingCentreController::updateProfileCentre):
 *
 *  - Equipment updates used to be scoped only by `where('profile_center_id',
 *    $eq['id'])` — using the equipment row's own id in place of the centre's
 *    id. That silently no-ops when nothing coincidentally shares that id, or
 *    worse, updates an unrelated row that does. Fixed to scope by both the
 *    centre id and the row id.
 *  - Services now also accept the richer fields (name/description/unit/
 *    nbr_place/is_refundable) the admin modal's expandable row sends, not
 *    just price/is_available. `unit` also now accepts "" ("no unit").
 */
class AdminCentreProfileUpdateTest extends TestCase
{
    use DatabaseTransactions;

    private function createAdmin(): User
    {
        return User::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role_id' => 6,
        ]);
    }

    private function makeCentre(string $nom): array
    {
        $owner = User::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'Owner',
            'last_name' => $nom,
            'email' => strtolower($nom) . '@example.com',
            'password' => bcrypt('password'),
            'role_id' => 3,
        ]);

        $profile = Profile::create([
            'user_id' => $owner->id,
            'type' => 'centre',
        ]);

        $profileCentre = ProfileCentre::create([
            'profile_id' => $profile->id,
        ]);

        $centre = CampingCentre::create([
            'nom' => $nom,
            'user_id' => $owner->id,
            'profile_centre_id' => $profileCentre->id,
        ]);

        return [$centre, $profileCentre];
    }

    public function test_equipment_update_does_not_leak_into_an_unrelated_row_with_a_colliding_profile_center_id(): void
    {
        $admin = $this->createAdmin();
        [$centreA, $profileCentreA] = $this->makeCentre('Centre A');
        [$centreB, $profileCentreB] = $this->makeCentre('Centre B');

        // Deliberately craft the exact collision shape found in production:
        // eqA's own primary key is forced to equal profileCentreB's id, so
        // the old buggy query (`where('profile_center_id', $eq['id'])`, i.e.
        // "where profile_center_id = profile_centre_B.id") matches the
        // *victim* row below instead of eqA itself — reproducing a real
        // cross-centre id collision, not just a hypothetical one.
        $eqAId = DB::table('profile_center_equipment')->insertGetId([
            'id' => $profileCentreB->id,
            'profile_center_id' => $profileCentreA->id,
            'type' => 'wifi',
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $victimId = DB::table('profile_center_equipment')->insertGetId([
            'profile_center_id' => $profileCentreB->id,
            'type' => 'parking',
            'is_available' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/centres/{$centreA->id}/profile-centre", [
            'equipment' => [
                ['id' => $eqAId, 'is_available' => false],
            ],
        ]);

        $response->assertOk();

        $this->assertFalse(
            ProfileCenterEquipment::find($eqAId)->is_available,
            'The targeted equipment row should have been flipped to unavailable.'
        );

        $this->assertTrue(
            ProfileCenterEquipment::find($victimId)->is_available,
            'A different equipment row that only coincidentally shares an id with the target (via profile_center_id) must be left untouched.'
        );
    }

    public function test_service_update_accepts_rich_fields_and_no_unit(): void
    {
        $admin = $this->createAdmin();
        [$centre, $profileCentre] = $this->makeCentre('Centre C');

        $service = ProfileCenterService::create([
            'profile_center_id' => $profileCentre->id,
            'name' => 'Cabin Rental',
            'price' => 40,
            'unit' => 'night',
            'description' => 'old description',
            'is_standard' => false,
            'is_available' => true,
            'nbr_place' => 2,
            'is_refundable' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/centres/{$centre->id}/profile-centre", [
            'services' => [[
                'id' => $service->id,
                'price' => 55,
                'is_available' => true,
                'name' => 'Deluxe Cabin',
                'description' => 'new description',
                'unit' => '',
                'nbr_place' => 6,
                'is_refundable' => false,
            ]],
        ]);

        $response->assertOk();

        $fresh = $service->fresh();
        $this->assertEquals(55, (float) $fresh->price);
        $this->assertEquals('Deluxe Cabin', $fresh->name);
        $this->assertEquals('new description', $fresh->description);
        // Laravel's global ConvertEmptyStringsToNull middleware turns "" into
        // null before it reaches the controller — that's the idiomatic "no
        // unit" representation for a nullable column, and every "unit &&"
        // truthiness check on the frontend treats null the same as "".
        $this->assertNull($fresh->unit);
        $this->assertEquals(6, $fresh->nbr_place);
        $this->assertFalse($fresh->is_refundable);
    }

    public function test_service_update_still_accepts_legacy_minimal_payload(): void
    {
        $admin = $this->createAdmin();
        [$centre, $profileCentre] = $this->makeCentre('Centre D');

        $service = ProfileCenterService::create([
            'profile_center_id' => $profileCentre->id,
            'name' => 'Breakfast',
            'price' => 10,
            'unit' => 'person',
            'is_standard' => false,
            'is_available' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->patchJson("/api/admin/centres/{$centre->id}/profile-centre", [
            'services' => [[
                'id' => $service->id,
                'price' => 12,
                'is_available' => false,
            ]],
        ]);

        $response->assertOk();

        $fresh = $service->fresh();
        $this->assertEquals(12, (float) $fresh->price);
        $this->assertFalse($fresh->is_available);
        // Untouched fields must survive a minimal payload.
        $this->assertEquals('Breakfast', $fresh->name);
        $this->assertEquals('person', $fresh->unit);
    }
}
