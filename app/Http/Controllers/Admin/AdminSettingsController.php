<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCommissionsRequest;
use App\Http\Requests\Admin\UpdatePaymentSettingsRequest;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /** Keys managed by the payment endpoint (non-sensitive subset exposed to public) */
    private const PAYMENT_KEYS = [
        'payment_link_flouci',
        'manual_payment_enabled',
        'deposit_min_percentage',
        'deposit_max_percentage',
        'deposit_min_total',
        // Direct bank-transfer recharge
        'bank_transfer_enabled',
        'bank_account_holder',
        'bank_account_bank_name',
        'bank_account_rib',
        'bank_account_iban',
        'bank_account_instructions',
    ];

    private const PAYMENT_DEFAULTS = [
        'payment_link_flouci' => '',
        'manual_payment_enabled' => false,
        'deposit_min_percentage' => 20,
        'deposit_max_percentage' => 80,
        'deposit_min_total' => 150,
        'bank_transfer_enabled' => false,
        'bank_account_holder' => '',
        'bank_account_bank_name' => '',
        'bank_account_rib' => '',
        'bank_account_iban' => '',
        'bank_account_instructions' => '',
    ];

    /**
     * Keys exposed publicly. Booking modals need the deposit rules; the wallet
     * recharge screen needs the bank-account details so campers can transfer.
     * The Flouci link stays admin-only (returned with the recharge request instead).
     */
    private const PAYMENT_PUBLIC_KEYS = [
        'manual_payment_enabled',
        'deposit_min_percentage',
        'deposit_max_percentage',
        'deposit_min_total',
        'bank_transfer_enabled',
        'bank_account_holder',
        'bank_account_bank_name',
        'bank_account_rib',
        'bank_account_iban',
        'bank_account_instructions',
    ];

    /** Keys managed by the commissions endpoint */
    private const COMMISSION_KEYS = [
        'commission_camper',
        'commission_center',
        'commission_group',
        'commission_supplier',
        'commission_guide',
        'service_fee_camper',
        'withdrawal_fee_percentage',
        'withdrawal_min_amount',
        'withdrawal_processing_days',
        'withdrawal_allowed_days',
        'withdrawal_enabled',
        'gateway_konnect_enabled',
        'gateway_flouci_enabled',
    ];

    /** Default values when a key is missing from the DB */
    private const COMMISSION_DEFAULTS = [
        'commission_camper' => 2,
        'commission_center' => 8,
        'commission_group' => 5,
        'commission_supplier' => 10,
        'commission_guide' => 7,
        'service_fee_camper' => 3,
        'withdrawal_fee_percentage' => 2,
        'withdrawal_min_amount' => 20,
        'withdrawal_processing_days' => 3,
        'withdrawal_allowed_days' => [1, 4],
        'withdrawal_enabled' => true,
        'gateway_konnect_enabled' => true,
        'gateway_flouci_enabled' => false,
    ];

    /**
     * GET /admin/settings
     * Return all settings grouped by category.
     */
    public function index(): JsonResponse
    {
        $settings = PlatformSetting::all()->map(fn ($s) => [
            'id' => $s->id,
            'key' => $s->key,
            'value' => $this->castValue($s),
            'raw_value' => $s->value,
            'label' => $s->label,
            'description' => $s->description,
            'type' => $s->type,
            'group' => $s->group,
        ])->groupBy('group');

        return response()->json(['data' => $settings]);
    }

    /**
     * GET /settings/public
     * Public-facing settings for booking modals — no auth required.
     */
    public function publicSettings(): JsonResponse
    {
        $keys = self::COMMISSION_KEYS;
        $data = [];

        foreach ($keys as $key) {
            $raw = PlatformSetting::get($key);
            $data[$key] = $raw ?? self::COMMISSION_DEFAULTS[$key] ?? null;
        }

        // Ensure platform name
        $data['platform_name'] = PlatformSetting::get('platform_name', 'TunisiaCamp');

        // Payment settings needed by booking modals (flouci link excluded — admin-only)
        foreach (self::PAYMENT_PUBLIC_KEYS as $key) {
            $raw = PlatformSetting::get($key);
            $data[$key] = $raw ?? self::PAYMENT_DEFAULTS[$key] ?? null;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * GET /admin/settings/payment
     * Full payment settings including the Flouci link (admin-only).
     */
    public function getPaymentSettings(): JsonResponse
    {
        $data = [];
        foreach (self::PAYMENT_KEYS as $key) {
            $raw = PlatformSetting::get($key);
            $data[$key] = $raw ?? self::PAYMENT_DEFAULTS[$key] ?? null;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /admin/settings/payment
     * Update payment settings with admin password verification.
     */
    public function updatePaymentSettings(UpdatePaymentSettingsRequest $request): JsonResponse
    {
        $request->validated();

        $expected = (string) config('admin.action_password');
        if ($expected === '' || !hash_equals($expected, (string) $request->password)) {
            return response()->json(['message' => 'Mot de passe incorrect.'], 403);
        }

        // Validate percentage order when both are present
        $minPct = $request->input('deposit_min_percentage');
        $maxPct = $request->input('deposit_max_percentage');
        if ($minPct !== null && $maxPct !== null && (int) $minPct >= (int) $maxPct) {
            return response()->json(['message' => 'Le pourcentage minimum doit être inférieur au maximum.'], 422);
        }

        foreach (self::PAYMENT_KEYS as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            $setting = PlatformSetting::where('key', $key)->first();

            if (!$setting) {
                $setting = PlatformSetting::create([
                    'key' => $key,
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'type' => $this->inferPaymentType($key),
                    'group' => 'payment',
                    'value' => null,
                ]);
            }

            $setting->update(['value' => $this->encodeValue($setting->type, $value)]);
        }

        return response()->json(['message' => 'Paramètres de paiement enregistrés avec succès.']);
    }

    /**
     * PUT /admin/settings
     * Bulk update settings. Body: { settings: { key: value } }
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $updates = $request->validated();

        foreach ($updates['settings'] as $key => $value) {
            $setting = PlatformSetting::where('key', $key)->first();
            if (!$setting) {
                continue;
            }

            if ($setting->type === 'json' && is_array($value)) {
                $encoded = json_encode(array_values(array_map('intval', $value)));
            } elseif ($setting->type === 'boolean') {
                $encoded = $value ? '1' : '0';
            } else {
                $encoded = (string) $value;
            }

            $setting->update(['value' => $encoded]);
        }

        return response()->json(['message' => 'Paramètres mis à jour avec succès.']);
    }

    /**
     * GET /admin/settings/commissions
     * Return commission, fee and gateway settings as a flat key→value map.
     */
    public function getCommissions(): JsonResponse
    {
        $data = [];

        foreach (self::COMMISSION_KEYS as $key) {
            $raw = PlatformSetting::get($key);
            $data[$key] = $raw ?? self::COMMISSION_DEFAULTS[$key] ?? null;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * PUT /admin/settings/commissions
     * Update commission/fee/gateway settings with admin password verification.
     */
    public function updateCommissions(UpdateCommissionsRequest $request): JsonResponse
    {
        $request->validated();

        // Verify admin password (same pattern used in AdminPaymentController)
        $expected = (string) config('admin.action_password');
        if ($expected === '' || !hash_equals($expected, (string) $request->password)) {
            return response()->json(['message' => 'Mot de passe incorrect.'], 403);
        }

        foreach (self::COMMISSION_KEYS as $key) {
            if (!$request->has($key)) {
                continue;
            }

            $value = $request->input($key);
            $setting = PlatformSetting::where('key', $key)->first();

            if (!$setting) {
                // Auto-create the row with sensible metadata
                $setting = PlatformSetting::create([
                    'key' => $key,
                    'label' => ucwords(str_replace('_', ' ', $key)),
                    'description' => null,
                    'type' => $this->inferType($key),
                    'group' => $this->inferGroup($key),
                    'value' => null,
                ]);
            }

            $setting->update(['value' => $this->encodeValue($setting->type, $value)]);
        }

        return response()->json(['message' => 'Configuration des commissions enregistrée avec succès.']);
    }

    /* ── Private helpers ─────────────────────────────────────────────── */

    private function castValue(PlatformSetting $s): mixed
    {
        return match ($s->type) {
            'boolean' => filter_var($s->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $s->value,
            'float' => (float) $s->value,
            'json' => json_decode($s->value, true),
            default => $s->value,
        };
    }

    private function encodeValue(string $type, mixed $value): string
    {
        return match ($type) {
            'json' => is_array($value)
                            ? json_encode(array_values(array_map('intval', $value)))
                            : (string) $value,
            'boolean' => $value ? '1' : '0',
            'float' => (string) (float) $value,
            default => (string) $value,
        };
    }

    private function inferType(string $key): string
    {
        if (str_ends_with($key, '_enabled')) {
            return 'boolean';
        }
        if ($key === 'withdrawal_allowed_days') {
            return 'json';
        }
        if ($key === 'withdrawal_min_amount') {
            return 'float';
        }

        return 'integer';
    }

    private function inferGroup(string $key): string
    {
        if (str_starts_with($key, 'commission_') || $key === 'service_fee_camper') {
            return 'commissions';
        }
        if (str_starts_with($key, 'withdrawal_')) {
            return 'withdrawal';
        }
        if (str_starts_with($key, 'gateway_')) {
            return 'gateway';
        }

        return 'general';
    }

    private function inferPaymentType(string $key): string
    {
        if (str_ends_with($key, '_enabled')) {
            return 'boolean';
        }
        if (in_array($key, ['deposit_min_percentage', 'deposit_max_percentage', 'deposit_min_total'])) {
            return 'integer';
        }

        return 'string';
    }
}
