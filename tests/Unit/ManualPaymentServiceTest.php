<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\ManualPaymentService;
use App\Models\PlatformSetting;
use App\Models\ProviderPaymentPreference;
use App\Models\User;

/**
 * Unit tests for ManualPaymentService — amount computation, enabled flag, validation.
 */
class ManualPaymentServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setting(string $key, string $value, string $type): void
    {
        // platform_settings rows are pre-seeded by migration, so upsert (not create).
        PlatformSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value, 'type' => $type, 'label' => $key, 'group' => 'payment', 'description' => ''],
        );
    }

    private function createProvider(string $email = 'provider@example.com'): User
    {
        return User::create([
            'first_name' => 'Provider',
            'last_name'  => 'Test',
            'email'      => $email,
            'password'   => bcrypt('password'),
            'role_id'    => 5,
        ]);
    }

    // ── isEnabled ──────────────────────────────────────────────────────────────

    public function test_is_enabled_returns_false_when_setting_absent(): void
    {
        $this->assertFalse(ManualPaymentService::isEnabled());
    }

    public function test_is_enabled_returns_true_when_setting_on(): void
    {
        $this->setting('manual_payment_enabled', '1', 'boolean');
        $this->assertTrue(ManualPaymentService::isEnabled());
    }

    public function test_is_enabled_returns_false_when_setting_is_zero(): void
    {
        $this->setting('manual_payment_enabled', '0', 'boolean');
        $this->assertFalse(ManualPaymentService::isEnabled());
    }

    // ── flouciLink ─────────────────────────────────────────────────────────────

    public function test_flouci_link_returns_empty_string_when_absent(): void
    {
        $this->assertSame('', ManualPaymentService::flouciLink());
    }

    public function test_flouci_link_returns_configured_url(): void
    {
        $this->setting('payment_link_flouci', 'https://pay.flouci.app', 'string');
        $this->assertSame('https://pay.flouci.app', ManualPaymentService::flouciLink());
    }

    // ── computeAmounts ─────────────────────────────────────────────────────────

    private function depositSettings(): void
    {
        $this->setting('deposit_min_total',      '150', 'integer');
        $this->setting('deposit_min_percentage', '20',  'integer');
        $this->setting('deposit_max_percentage', '80',  'integer');
    }

    public function test_compute_amounts_returns_full_when_provider_rejects_deposits(): void
    {
        $this->depositSettings();
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => false,
            'deposit_percentage'=> 30,
        ]);

        $result = ManualPaymentService::computeAmounts($provider->id, 500.0);

        $this->assertSame('full', $result['payment_option']);
        $this->assertSame(500.0, $result['amount_now']);
        $this->assertSame(0.0,   $result['amount_later']);
        $this->assertNull($result['deposit_pct']);
    }

    public function test_compute_amounts_returns_full_when_total_below_minimum(): void
    {
        $this->depositSettings();
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => true,
            'deposit_percentage'=> 30,
        ]);

        // 100 < 150 minimum
        $result = ManualPaymentService::computeAmounts($provider->id, 100.0);

        $this->assertSame('full', $result['payment_option']);
    }

    public function test_compute_amounts_returns_deposit_split_when_eligible(): void
    {
        $this->depositSettings();
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => true,
            'deposit_percentage'=> 30,
        ]);

        $result = ManualPaymentService::computeAmounts($provider->id, 500.0);

        $this->assertSame('deposit', $result['payment_option']);
        $this->assertSame(150.0,    $result['amount_now']);   // 30% of 500
        $this->assertSame(350.0,    $result['amount_later']); // 70% of 500
        $this->assertSame(30,       $result['deposit_pct']);
    }

    public function test_compute_amounts_clamps_pct_to_min(): void
    {
        $this->depositSettings(); // min = 20
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => true,
            'deposit_percentage'=> 10, // below minimum of 20
        ]);

        $result = ManualPaymentService::computeAmounts($provider->id, 500.0);

        // Should clamp to 20%
        $this->assertSame(100.0, $result['amount_now']);  // 20% of 500
        $this->assertSame(400.0, $result['amount_later']);
    }

    public function test_compute_amounts_clamps_pct_to_max(): void
    {
        $this->depositSettings(); // max = 80
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => true,
            'deposit_percentage'=> 90, // above maximum of 80
        ]);

        $result = ManualPaymentService::computeAmounts($provider->id, 500.0);

        // Should clamp to 80%
        $this->assertSame(400.0, $result['amount_now']);  // 80% of 500
        $this->assertSame(100.0, $result['amount_later']);
    }

    // ── validateOption ─────────────────────────────────────────────────────────

    public function test_validate_option_accepts_full_payment(): void
    {
        $this->setting('deposit_min_total', '150', 'integer');
        $provider = $this->createProvider();

        $this->assertNull(ManualPaymentService::validateOption('full', $provider->id, 500.0));
    }

    public function test_validate_option_rejects_unknown_option(): void
    {
        $provider = $this->createProvider();
        $error    = ManualPaymentService::validateOption('unknown', $provider->id, 500.0);

        $this->assertNotNull($error);
    }

    public function test_validate_option_rejects_deposit_for_provider_that_disallows_it(): void
    {
        $this->setting('deposit_min_total', '150', 'integer');
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => false,
            'deposit_percentage'=> 30,
        ]);

        $error = ManualPaymentService::validateOption('deposit', $provider->id, 500.0);

        $this->assertNotNull($error);
        $this->assertStringContainsString('deposit', strtolower($error));
    }

    public function test_validate_option_rejects_deposit_when_total_too_low(): void
    {
        $this->setting('deposit_min_total', '150', 'integer');
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => true,
            'deposit_percentage'=> 30,
        ]);

        // Total is 100, minimum is 150
        $error = ManualPaymentService::validateOption('deposit', $provider->id, 100.0);

        $this->assertNotNull($error);
    }

    public function test_validate_option_accepts_deposit_when_eligible(): void
    {
        $this->setting('deposit_min_total', '150', 'integer');
        $provider = $this->createProvider();
        ProviderPaymentPreference::create([
            'user_id'           => $provider->id,
            'accepts_deposits'  => true,
            'deposit_percentage'=> 30,
        ]);

        $this->assertNull(ManualPaymentService::validateOption('deposit', $provider->id, 500.0));
    }
}
