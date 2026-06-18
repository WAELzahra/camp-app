<?php

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use App\Models\User;
use App\Models\WalletRechargeRequest;
use App\Models\PlatformSetting;

/**
 * Tests for WalletRechargeController (initiate, submit, list).
 */
class WalletRechargeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function createCamper(string $email = 'camper@example.com'): User
    {
        return User::create([
            'first_name' => 'Wallet',
            'last_name'  => 'Tester',
            'email'      => $email,
            'password'   => bcrypt('password'),
            'role_id'    => 3,
        ]);
    }

    private function enableManual(): void
    {
        // platform_settings rows are pre-seeded by migration, so upsert (not insert).
        PlatformSetting::updateOrCreate(['key' => 'manual_payment_enabled'], ['value' => '1', 'type' => 'boolean', 'label' => 'Manual', 'group' => 'payment']);
        PlatformSetting::updateOrCreate(['key' => 'payment_link_flouci'], ['value' => 'https://flouci.example.com', 'type' => 'string', 'label' => 'Flouci', 'group' => 'payment']);
    }

    private function makeRechargeRequest(int $userId, array $attrs = []): WalletRechargeRequest
    {
        return WalletRechargeRequest::create(array_merge([
            'user_id'           => $userId,
            'amount'            => 100.0,
            'payment_reference' => 'WALLET-' . $userId . '-' . time(),
            'status'            => 'pending',
        ], $attrs));
    }

    // ── initiate ───────────────────────────────────────────────────────────────

    public function test_initiate_creates_pending_recharge_request(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();

        Sanctum::actingAs($camper);
        $response = $this->postJson('/api/my/wallet/recharge', ['amount' => 150]);

        $response->assertCreated()
            ->assertJsonPath('data.amount', fn ($v) => (float) $v === 150.0)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('wallet_recharge_requests', [
            'user_id' => $camper->id,
            'amount'  => 150.0,
            'status'  => 'pending',
        ]);
    }

    public function test_initiate_returns_payment_reference_and_flouci_link(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();

        Sanctum::actingAs($camper);
        $response = $this->postJson('/api/my/wallet/recharge', ['amount' => 200]);

        $response->assertCreated()
            ->assertJsonPath('data.flouci_link', 'https://flouci.example.com');

        $this->assertNotNull($response->json('data.payment_reference'));
        $this->assertStringStartsWith('WALLET-', $response->json('data.payment_reference'));
    }

    public function test_initiate_fails_when_manual_disabled(): void
    {
        // No settings inserted = disabled by default
        $camper = $this->createCamper();

        Sanctum::actingAs($camper);
        $this->postJson('/api/my/wallet/recharge', ['amount' => 100])
            ->assertStatus(422);
    }

    public function test_initiate_validates_minimum_amount(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();

        Sanctum::actingAs($camper);
        $this->postJson('/api/my/wallet/recharge', ['amount' => 0])
            ->assertStatus(422);
    }

    public function test_initiate_validates_maximum_amount(): void
    {
        $this->enableManual();
        $camper = $this->createCamper();

        Sanctum::actingAs($camper);
        $this->postJson('/api/my/wallet/recharge', ['amount' => 10001])
            ->assertStatus(422);
    }

    public function test_initiate_requires_authentication(): void
    {
        $this->postJson('/api/my/wallet/recharge', ['amount' => 100])
            ->assertStatus(401);
    }

    // ── submit ─────────────────────────────────────────────────────────────────

    public function test_camper_can_submit_recharge_proof(): void
    {
        $camper = $this->createCamper();
        $req    = $this->makeRechargeRequest($camper->id, ['status' => 'pending']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/wallet/recharge/{$req->id}/submit")
            ->assertOk()
            ->assertJsonFragment(['status' => 'paiement_soumis']);

        $this->assertDatabaseHas('wallet_recharge_requests', [
            'id'     => $req->id,
            'status' => 'paiement_soumis',
        ]);
    }

    public function test_camper_can_resubmit_rejected_recharge(): void
    {
        $camper = $this->createCamper();
        $req    = $this->makeRechargeRequest($camper->id, ['status' => 'rejected']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/wallet/recharge/{$req->id}/submit")
            ->assertOk();

        $this->assertDatabaseHas('wallet_recharge_requests', ['id' => $req->id, 'status' => 'paiement_soumis']);
    }

    public function test_submit_422_if_already_submitted(): void
    {
        $camper = $this->createCamper();
        $req    = $this->makeRechargeRequest($camper->id, ['status' => 'paiement_soumis']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/wallet/recharge/{$req->id}/submit")
            ->assertStatus(422);
    }

    public function test_submit_422_if_already_confirmed(): void
    {
        $camper = $this->createCamper();
        $req    = $this->makeRechargeRequest($camper->id, ['status' => 'confirmed']);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/wallet/recharge/{$req->id}/submit")
            ->assertStatus(422);
    }

    public function test_submit_404_for_another_users_request(): void
    {
        $camper = $this->createCamper();
        $other  = $this->createCamper('other@example.com');
        $req    = $this->makeRechargeRequest($other->id);

        Sanctum::actingAs($camper);
        $this->postJson("/api/my/wallet/recharge/{$req->id}/submit")
            ->assertStatus(404);
    }

    // ── list ───────────────────────────────────────────────────────────────────

    public function test_camper_can_list_own_recharge_requests(): void
    {
        $camper = $this->createCamper();
        $this->makeRechargeRequest($camper->id, ['payment_reference' => 'R1', 'amount' => 100]);
        $this->makeRechargeRequest($camper->id, ['payment_reference' => 'R2', 'amount' => 200, 'status' => 'confirmed']);

        Sanctum::actingAs($camper);
        $this->getJson('/api/my/wallet/recharges')
            ->assertOk()
            ->assertJsonPath('total', 2);
    }

    public function test_list_only_returns_own_requests(): void
    {
        $camper = $this->createCamper();
        $other  = $this->createCamper('other@example.com');
        $this->makeRechargeRequest($camper->id, ['payment_reference' => 'MINE']);
        $this->makeRechargeRequest($other->id, ['payment_reference' => 'THEIRS']);

        Sanctum::actingAs($camper);
        $this->getJson('/api/my/wallet/recharges')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }
}
