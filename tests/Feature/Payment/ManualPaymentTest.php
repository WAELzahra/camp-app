<?php

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;
use App\Models\Reservations_centre;
use App\Models\PlatformSetting;

/**
 * Tests for ManualPaymentController (camper-side payment proof submission and payment info).
 * Uses Reservations_centre because it has the clearest 'pending' initial status.
 */
class ManualPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function createCamper(string $email = 'camper@example.com'): User
    {
        return User::create([
            'first_name' => 'Test',
            'last_name'  => 'Camper',
            'email'      => $email,
            'password'   => bcrypt('password'),
            'role_id'    => 3,
        ]);
    }

    private function enableManual(): void
    {
        PlatformSetting::insert([
            ['key' => 'manual_payment_enabled', 'value' => '1',                          'type' => 'boolean', 'label' => 'Manual',  'group' => 'payment', 'description' => '', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'payment_link_flouci',    'value' => 'https://flouci.example.com', 'type' => 'string',  'label' => 'Flouci',  'group' => 'payment', 'description' => '', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    private function makeCentreReservation(int $userId, array $attrs = []): Reservations_centre
    {
        return Reservations_centre::create(array_merge([
            'user_id'           => $userId,
            'centre_id'         => 999,
            'date_debut'        => now()->format('Y-m-d'),
            'date_fin'          => now()->addDays(3)->format('Y-m-d'),
            'nbr_place'         => 2,
            'payment_method'    => 'manual',
            'payment_option'    => 'full',
            'payment_reference' => 'CTR-TEST-001',
            'amount_now'        => 300.00,
            'amount_later'      => 0.00,
            'status'            => 'pending',
        ], $attrs));
    }

    // ── paymentInfo ────────────────────────────────────────────────────────────

    public function test_payment_info_returns_reference_and_amounts(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['amount_now' => 200.0]);

        Sanctum::actingAs($camper);
        $response = $this->getJson("/api/my/reservations/centres/{$res->id}/payment-info");

        $response->assertOk()
            ->assertJsonFragment(['reference' => 'CTR-TEST-001'])
            ->assertJsonPath('amount_now', 200.0)
            ->assertJsonPath('is_balance_step', false);
    }

    public function test_payment_info_swaps_amounts_on_balance_step(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, [
            'status'         => 'confirmée_solde_en_attente',
            'payment_option' => 'deposit',
            'amount_now'     => 90.0,
            'amount_later'   => 210.0,
        ]);

        Sanctum::actingAs($camper);
        $response = $this->getJson("/api/my/reservations/centres/{$res->id}/payment-info");

        $response->assertOk()
            ->assertJsonPath('is_balance_step', true)
            ->assertJsonPath('amount_now', 210.0)
            ->assertJsonPath('amount_later', 0);
    }

    public function test_payment_info_returns_flouci_link(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id);

        Sanctum::actingAs($camper);
        $this->getJson("/api/my/reservations/centres/{$res->id}/payment-info")
            ->assertOk()
            ->assertJsonPath('flouci_link', 'https://flouci.example.com');
    }

    public function test_payment_info_404_for_another_users_reservation(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();
        $other  = $this->createCamper('other@example.com');
        $res    = $this->makeCentreReservation($other->id);

        Sanctum::actingAs($camper);
        $this->getJson("/api/my/reservations/centres/{$res->id}/payment-info")
            ->assertStatus(404);
    }

    // ── submitProof ────────────────────────────────────────────────────────────

    public function test_camper_can_submit_initial_payment_proof(): void
    {
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['status' => 'pending']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertOk()
            ->assertJsonFragment(['status' => 'paiement_soumis']);

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $res->id,
            'status' => 'paiement_soumis',
        ]);
    }

    public function test_camper_can_resubmit_after_rejection(): void
    {
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['status' => 'paiement_invalide']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertOk()
            ->assertJsonFragment(['status' => 'paiement_soumis']);
    }

    public function test_camper_can_submit_balance_proof(): void
    {
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, [
            'status'         => 'confirmée_solde_en_attente',
            'payment_option' => 'deposit',
            'amount_now'     => 90.0,
            'amount_later'   => 210.0,
        ]);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertOk()
            ->assertJsonFragment(['status' => 'solde_soumis']);

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $res->id,
            'status' => 'solde_soumis',
        ]);
    }

    public function test_submit_proof_422_if_already_submitted(): void
    {
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['status' => 'paiement_soumis']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertStatus(422);
    }

    public function test_submit_proof_422_for_wallet_reservation(): void
    {
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['payment_method' => 'wallet']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertStatus(422);
    }

    public function test_submit_proof_404_for_another_users_reservation(): void
    {
        $camper = $this->createCamper();
        $other  = $this->createCamper('other@example.com');
        $res    = $this->makeCentreReservation($other->id);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertStatus(404);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id);

        $this->postJson("/api/my/reservations/centres/{$res->id}/payment-submitted")
            ->assertStatus(401);
    }
}
