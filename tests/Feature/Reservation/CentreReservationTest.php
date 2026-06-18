<?php

namespace Tests\Feature\Reservation;

use App\Models\Reservations_centre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Locks the public API contract for the centre reservation flow
 * (create / confirm / cancel / show) so later refactors — Form Requests,
 * service extraction, queues — cannot silently change behaviour the
 * frontend depends on.
 *
 * Uses a manual-payment reservation throughout to keep wallet escrow out
 * of scope; those paths are covered by the Payment test suite.
 */
class CentreReservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeReservation(int $camperId, int $centreId, array $attrs = []): Reservations_centre
    {
        return Reservations_centre::create(array_merge([
            'user_id' => $camperId,
            'centre_id' => $centreId,
            'date_debut' => now()->addDays(5)->format('Y-m-d'),
            'date_fin' => now()->addDays(8)->format('Y-m-d'),
            'nbr_place' => 2,
            'payment_method' => 'manual',
            'payment_option' => 'full',
            'payment_reference' => 'CTR-FEAT-'.uniqid(),
            'total_price' => 0,
            'status' => 'pending',
        ], $attrs));
    }

    // ── store: auth & authorization ──────────────────────────────────────────────

    public function test_store_requires_authentication(): void
    {
        $this->postJson('/api/reservation/centre', [])->assertStatus(401);
    }

    public function test_store_is_forbidden_for_non_campers(): void
    {
        Sanctum::actingAs(User::factory()->centre()->create());

        $this->postJson('/api/reservation/centre', [])->assertStatus(403);
    }

    // ── store: validation contract ───────────────────────────────────────────────

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs(User::factory()->camper()->create());

        $this->postJson('/api/reservation/centre', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'centre_id', 'date_debut', 'date_fin', 'nbr_place', 'service_items',
            ]);
    }

    public function test_store_rejects_a_nonexistent_centre(): void
    {
        Sanctum::actingAs(User::factory()->camper()->create());

        $this->postJson('/api/reservation/centre', [
            'centre_id' => 999999,
            'date_debut' => now()->addDay()->format('Y-m-d'),
            'date_fin' => now()->addDays(2)->format('Y-m-d'),
            'nbr_place' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['centre_id']);
    }

    public function test_store_rejects_end_date_before_start_date(): void
    {
        Sanctum::actingAs(User::factory()->camper()->create());
        $centre = User::factory()->centre()->create();

        $this->postJson('/api/reservation/centre', [
            'centre_id' => $centre->id,
            'date_debut' => now()->addDays(5)->format('Y-m-d'),
            'date_fin' => now()->addDays(2)->format('Y-m-d'),
            'nbr_place' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors(['date_fin']);
    }

    // ── confirm (centre) ─────────────────────────────────────────────────────────

    public function test_centre_can_confirm_a_pending_reservation(): void
    {
        Mail::fake();
        $camper = User::factory()->camper()->create();
        $centre = User::factory()->centre()->create();
        $res = $this->makeReservation($camper->id, $centre->id, ['status' => 'pending']);

        Sanctum::actingAs($centre);
        $this->patchJson("/api/reservation/centre/confirm/{$res->id}")->assertOk();

        $this->assertDatabaseHas('reservations_centres', [
            'id' => $res->id,
            'status' => 'approved',
        ]);
    }

    public function test_confirm_is_forbidden_for_a_different_centre(): void
    {
        $camper = User::factory()->camper()->create();
        $centre = User::factory()->centre()->create();
        $otherCentre = User::factory()->centre()->create();
        $res = $this->makeReservation($camper->id, $centre->id);

        Sanctum::actingAs($otherCentre);
        $this->patchJson("/api/reservation/centre/confirm/{$res->id}")->assertStatus(403);

        $this->assertDatabaseHas('reservations_centres', [
            'id' => $res->id,
            'status' => 'pending',
        ]);
    }

    // ── cancel (destroy) ─────────────────────────────────────────────────────────

    public function test_camper_can_cancel_own_reservation(): void
    {
        Mail::fake();
        $camper = User::factory()->camper()->create();
        $centre = User::factory()->centre()->create();
        $res = $this->makeReservation($camper->id, $centre->id, ['status' => 'pending']);

        Sanctum::actingAs($camper);
        $this->patchJson("/api/reservation/centre/destroy/{$res->id}")
            ->assertOk()
            ->assertJsonPath('canceled_by', 'user');

        $this->assertDatabaseHas('reservations_centres', [
            'id' => $res->id,
            'status' => 'canceled',
        ]);
    }

    public function test_cancel_is_forbidden_for_an_unrelated_camper(): void
    {
        $camper = User::factory()->camper()->create();
        $other = User::factory()->camper()->create();
        $centre = User::factory()->centre()->create();
        $res = $this->makeReservation($camper->id, $centre->id);

        Sanctum::actingAs($other);
        $this->patchJson("/api/reservation/centre/destroy/{$res->id}")->assertStatus(403);
    }

    // ── show ─────────────────────────────────────────────────────────────────────

    public function test_show_returns_the_reservation_for_an_authenticated_user(): void
    {
        $camper = User::factory()->camper()->create();
        $centre = User::factory()->centre()->create();
        $res = $this->makeReservation($camper->id, $centre->id);

        Sanctum::actingAs($camper);
        $this->getJson("/api/reservation/{$res->id}")
            ->assertOk()
            ->assertJsonPath('reservation.id', $res->id);
    }

    public function test_show_returns_404_for_a_missing_reservation(): void
    {
        Sanctum::actingAs(User::factory()->camper()->create());

        $this->getJson('/api/reservation/999999')->assertStatus(404);
    }
}
