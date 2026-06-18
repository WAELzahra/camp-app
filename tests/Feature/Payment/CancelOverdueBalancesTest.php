<?php

namespace Tests\Feature\Payment;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Tests for the payments:cancel-overdue-balances Artisan command.
 */
class CancelOverdueBalancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Notification::fake();
        Event::fake();
    }

    protected function tearDown(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        parent::tearDown();
    }

    private function createCamper(string $email = 'camper@example.com'): User
    {
        // uuid is set explicitly because Event::fake() disables the model's creating hook.
        return User::create([
            'uuid'       => (string) \Illuminate\Support\Str::uuid(),
            'first_name' => 'Test',
            'last_name'  => 'Camper',
            'email'      => $email,
            'password'   => bcrypt('password'),
            'role_id'    => 3,
        ]);
    }

    private function insertEventReservation(int $userId, array $attrs = []): int
    {
        return DB::table('reservations_events')->insertGetId(array_merge([
            'user_id'           => $userId,
            'event_id'          => 999,
            'group_id'          => 998,
            'name'              => 'Test Camper',
            'nbr_place'         => 1,
            'payment_method'    => 'manual',
            'payment_option'    => 'deposit',
            'payment_reference' => 'EVT-OVR-001',
            'amount_now'        => 60.0,
            'amount_later'      => 140.0,
            'status'            => 'confirmée_solde_en_attente',
            'balance_due_at'    => now()->subDay()->format('Y-m-d'),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $attrs));
    }

    private function insertCentreReservation(int $userId, array $attrs = []): int
    {
        return DB::table('reservations_centres')->insertGetId(array_merge([
            'user_id'           => $userId,
            'centre_id'         => 999,
            'date_debut'        => now()->format('Y-m-d'),
            'date_fin'          => now()->addDay()->format('Y-m-d'),
            'nbr_place'         => 2,
            'payment_method'    => 'manual',
            'payment_option'    => 'deposit',
            'payment_reference' => 'CTR-OVR-001',
            'amount_now'        => 100.0,
            'amount_later'      => 200.0,
            'status'            => 'confirmée_solde_en_attente',
            'balance_due_at'    => now()->subDays(2)->format('Y-m-d'),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $attrs));
    }

    private function insertMaterielReservation(int $userId, array $attrs = []): int
    {
        return DB::table('reservations_materielles')->insertGetId(array_merge([
            'user_id'           => $userId,
            'materielle_id'     => 999,
            'fournisseur_id'    => 998,
            'type_reservation'  => 'location',
            'quantite'          => 1,
            'montant_total'     => 300.0,
            'mode_livraison'    => 'pickup',
            'payment_method'    => 'manual',
            'payment_option'    => 'deposit',
            'payment_reference' => 'MAT-OVR-001',
            'amount_now'        => 90.0,
            'amount_later'      => 210.0,
            'status'            => 'confirmée_solde_en_attente',
            'balance_due_at'    => now()->subDay()->format('Y-m-d'),
            'created_at'        => now(),
            'updated_at'        => now(),
        ], $attrs));
    }

    // ── cancellation tests ─────────────────────────────────────────────────────

    public function test_command_cancels_overdue_event_reservation(): void
    {
        $camper = $this->createCamper();
        $id     = $this->insertEventReservation($camper->id);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        $this->assertDatabaseHas('reservations_events', [
            'id'     => $id,
            'status' => 'annulée_solde_impayé',
        ]);
    }

    public function test_command_cancels_overdue_centre_reservation(): void
    {
        $camper = $this->createCamper();
        $id     = $this->insertCentreReservation($camper->id);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        $this->assertDatabaseHas('reservations_centres', [
            'id'     => $id,
            'status' => 'annulée_solde_impayé',
        ]);
    }

    public function test_command_cancels_overdue_materiel_reservation(): void
    {
        $camper = $this->createCamper();
        $id     = $this->insertMaterielReservation($camper->id);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        $this->assertDatabaseHas('reservations_materielles', [
            'id'     => $id,
            'status' => 'annulée_solde_impayé',
        ]);
    }

    // ── non-cancellation tests ─────────────────────────────────────────────────

    public function test_command_does_not_cancel_future_due_reservations(): void
    {
        $camper = $this->createCamper();
        $id     = $this->insertEventReservation($camper->id, [
            'balance_due_at'    => now()->addDays(7)->format('Y-m-d'),
            'payment_reference' => 'EVT-FUT-001',
        ]);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        $this->assertDatabaseHas('reservations_events', [
            'id'     => $id,
            'status' => 'confirmée_solde_en_attente',
        ]);
    }

    public function test_command_does_not_cancel_fully_paid_reservations(): void
    {
        $camper = $this->createCamper();
        $id     = $this->insertEventReservation($camper->id, [
            'status'            => 'entièrement_payée',
            'payment_reference' => 'EVT-PAID-001',
        ]);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        $this->assertDatabaseHas('reservations_events', [
            'id'     => $id,
            'status' => 'entièrement_payée',
        ]);
    }

    public function test_command_does_not_cancel_balance_submitted_reservations(): void
    {
        // solde_soumis means the camper paid — admin just hasn't confirmed yet
        $camper = $this->createCamper();
        $id     = $this->insertEventReservation($camper->id, [
            'status'            => 'solde_soumis',
            'payment_reference' => 'EVT-SUB-001',
        ]);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        $this->assertDatabaseHas('reservations_events', [
            'id'     => $id,
            'status' => 'solde_soumis',
        ]);
    }

    // ── output and notification tests ──────────────────────────────────────────

    public function test_command_reports_cancelled_count_in_output(): void
    {
        $camper = $this->createCamper();
        $this->insertEventReservation($camper->id, ['payment_reference' => 'EVT-C1']);
        $this->insertCentreReservation($camper->id, ['payment_reference' => 'CTR-C1']);

        $this->artisan('payments:cancel-overdue-balances')
            ->expectsOutputToContain('2 overdue balance')
            ->assertExitCode(0);
    }

    public function test_command_sends_notification_to_camper(): void
    {
        $camper = $this->createCamper();
        $this->insertEventReservation($camper->id);

        $this->artisan('payments:cancel-overdue-balances')->assertExitCode(0);

        Notification::assertSentTo($camper, \App\Notifications\CustomNotification::class);
    }

    public function test_command_succeeds_with_no_overdue_reservations(): void
    {
        $this->artisan('payments:cancel-overdue-balances')
            ->expectsOutputToContain('0 overdue')
            ->assertExitCode(0);
    }
}
