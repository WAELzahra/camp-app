<?php

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;
use App\Models\Reservations_centre;
use App\Models\WalletRechargeRequest;
use App\Models\Balance;

/**
 * Tests for AdminPaymentReviewController (confirm/reject reservations and wallet recharges).
 */
class AdminPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Notification::fake();
        Event::fake();
    }

    protected function tearDown(): void
    {
        \DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function createAdmin(): User
    {
        return User::create([
            'first_name' => 'Admin',
            'last_name'  => 'User',
            'email'      => 'admin@example.com',
            'password'   => bcrypt('password'),
            'role_id'    => 6,
        ]);
    }

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
            'payment_reference' => 'CTR-ADM-001',
            'amount_now'        => 300.00,
            'amount_later'      => 0.00,
            'status'            => 'paiement_soumis',
        ], $attrs));
    }

    private function makeWalletRequest(int $userId, array $attrs = []): WalletRechargeRequest
    {
        return WalletRechargeRequest::create(array_merge([
            'user_id'           => $userId,
            'amount'            => 500.0,
            'payment_reference' => 'WALLET-ADM-001',
            'status'            => 'paiement_soumis',
            'submitted_at'      => now(),
        ], $attrs));
    }

    // ── pending list ───────────────────────────────────────────────────────────

    public function test_pending_list_returns_submitted_reservations(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $this->makeCentreReservation($camper->id);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/payments/pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_pending_list_includes_wallet_recharges(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $this->makeWalletRequest($camper->id);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/payments/pending');

        $response->assertOk();
        $data = $response->json('data');
        $walletItems = array_filter($data, fn($item) => $item['type'] === 'wallet');
        $this->assertCount(1, $walletItems);
    }

    public function test_pending_list_requires_admin(): void
    {
        $camper = $this->createCamper();

        Sanctum::actingAs($camper);
        $this->getJson('/api/admin/payments/pending')
            ->assertStatus(403);
    }

    // ── confirm reservation ────────────────────────────────────────────────────

    public function test_admin_confirms_full_payment_to_approved(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, [
            'payment_option' => 'full',
            'amount_later'   => 0.0,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/centres/{$res->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $res->id,
            'status' => 'approved',
        ]);
    }

    public function test_admin_confirms_deposit_payment_sets_balance_pending(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, [
            'payment_option' => 'deposit',
            'amount_now'     => 90.0,
            'amount_later'   => 210.0,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/centres/{$res->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $res->id,
            'status' => 'confirmée_solde_en_attente',
        ]);
    }

    public function test_admin_confirms_balance_payment_sets_fully_paid(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, [
            'status'         => 'solde_soumis',
            'payment_option' => 'deposit',
            'amount_now'     => 90.0,
            'amount_later'   => 210.0,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/centres/{$res->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $res->id,
            'status' => 'entièrement_payée',
        ]);
    }

    public function test_confirm_422_when_not_in_submitted_state(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['status' => 'pending']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/centres/{$res->id}/confirm")
            ->assertStatus(422);
    }

    // ── reject reservation ─────────────────────────────────────────────────────

    public function test_admin_rejects_payment_sets_invalide_status(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/centres/{$res->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $res->id,
            'status' => 'paiement_invalide',
        ]);
    }

    public function test_reject_422_when_not_in_submitted_state(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $res    = $this->makeCentreReservation($camper->id, ['status' => 'pending']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/centres/{$res->id}/reject")
            ->assertStatus(422);
    }

    // ── confirm wallet recharge ────────────────────────────────────────────────

    public function test_admin_confirms_wallet_recharge_and_credits_balance(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $req    = $this->makeWalletRequest($camper->id, ['amount' => 500.0]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/confirm")
            ->assertOk();

        $this->assertDatabaseHas('wallet_recharge_requests', [
            'id'     => $req->id,
            'status' => 'confirmed',
        ]);

        $balance = Balance::where('user_id', $camper->id)->first();
        $this->assertNotNull($balance);
        $this->assertEquals(500.0, (float) $balance->solde_disponible);
    }

    public function test_wallet_confirm_logs_wallet_transaction(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $req    = $this->makeWalletRequest($camper->id, ['amount' => 250.0, 'payment_reference' => 'WALLET-TX-001']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/confirm")->assertOk();

        $this->assertDatabaseHas('wallet_transactions', [
            'user_id'        => $camper->id,
            'type'           => 'credit',
            'category'       => 'deposit',
            'amount_gross'   => 250.0,
            'reference_type' => 'wallet_recharge',
        ]);
    }

    public function test_wallet_confirm_404_for_non_submitted_request(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $req    = $this->makeWalletRequest($camper->id, ['status' => 'pending']);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/confirm")
            ->assertStatus(404);
    }

    // ── reject wallet recharge ─────────────────────────────────────────────────

    public function test_admin_rejects_wallet_recharge(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $req    = $this->makeWalletRequest($camper->id);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/reject")
            ->assertOk();

        $this->assertDatabaseHas('wallet_recharge_requests', [
            'id'     => $req->id,
            'status' => 'rejected',
        ]);
    }

    public function test_wallet_reject_does_not_credit_balance(): void
    {
        $admin  = $this->createAdmin();
        $camper = $this->createCamper();
        $req    = $this->makeWalletRequest($camper->id, ['amount' => 300.0]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/reject")->assertOk();

        $balance = Balance::where('user_id', $camper->id)->first();
        $this->assertTrue($balance === null || (float) $balance->solde_disponible === 0.0);
    }

    public function test_non_admin_cannot_confirm_or_reject(): void
    {
        $camper = $this->createCamper();
        $req    = $this->makeWalletRequest($camper->id);

        Sanctum::actingAs($camper);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/confirm")->assertStatus(403);
        $this->postJson("/api/admin/payments/wallet/{$req->id}/reject")->assertStatus(403);
    }
}
