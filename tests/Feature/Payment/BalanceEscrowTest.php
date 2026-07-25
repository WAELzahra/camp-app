<?php

namespace Tests\Feature\Payment;

use App\Models\Balance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression test for Balance::releaseEscrow/refundEscrow after replacing
 * string-interpolated DB::raw() with bound-parameter DB::update() calls
 * (SECURITY, 2026-07-25) — verifies the atomic GREATEST(0, ...) floor and the
 * disponible/en_attente movement still behave identically.
 */
class BalanceEscrowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function createUser(): User
    {
        return User::create([
            'uuid'       => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'Test',
            'last_name'  => 'Provider',
            'email'      => 'provider-'.uniqid().'@example.com',
            'password'   => bcrypt('password'),
            'role_id'    => 3,
        ]);
    }

    public function test_release_escrow_decrements_en_attente_and_floors_at_zero(): void
    {
        $user = $this->createUser();
        $balance = Balance::forUser($user->id);
        $balance->update(['solde_en_attente' => 100]);

        $balance->releaseEscrow(40);
        $this->assertEquals(60, (float) $balance->fresh()->solde_en_attente);

        // Releasing more than what's left must floor at 0, never go negative.
        $balance->releaseEscrow(1000);
        $this->assertEquals(0, (float) $balance->fresh()->solde_en_attente);
    }

    public function test_refund_escrow_moves_funds_to_disponible_and_floors_en_attente(): void
    {
        $user = $this->createUser();
        $balance = Balance::forUser($user->id);
        $balance->update(['solde_en_attente' => 50, 'solde_disponible' => 10]);

        $balance->refundEscrow(30);
        $fresh = $balance->fresh();
        $this->assertEquals(20, (float) $fresh->solde_en_attente);
        $this->assertEquals(40, (float) $fresh->solde_disponible);

        // Refunding more than what's held must still credit disponible in full
        // and floor en_attente at 0 (legacy reservations created via debiter()).
        $balance->refundEscrow(1000);
        $fresh = $balance->fresh();
        $this->assertEquals(0, (float) $fresh->solde_en_attente);
        $this->assertEquals(1040, (float) $fresh->solde_disponible);
    }

    public function test_montant_with_decimals_is_applied_precisely(): void
    {
        $user = $this->createUser();
        $balance = Balance::forUser($user->id);
        $balance->update(['solde_en_attente' => 99.99, 'solde_disponible' => 0]);

        $balance->refundEscrow(33.33);
        $fresh = $balance->fresh();
        $this->assertEquals(66.66, (float) $fresh->solde_en_attente);
        $this->assertEquals(33.33, (float) $fresh->solde_disponible);
    }
}
