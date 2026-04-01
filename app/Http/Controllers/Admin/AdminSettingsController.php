<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSettingsController extends Controller
{
    /**
     * GET /admin/settings
     * Return all settings as flat key→value map with metadata.
     */
    public function index(): JsonResponse
    {
        $settings = PlatformSetting::all()->map(fn($s) => [
            'id'          => $s->id,
            'key'         => $s->key,
            'value'       => $this->castValue($s),
            'raw_value'   => $s->value,
            'label'       => $s->label,
            'description' => $s->description,
            'type'        => $s->type,
            'group'       => $s->group,
        ])->groupBy('group');

        return response()->json(['data' => $settings]);
    }

    /**
     * GET /admin/settings/public
     * Return minimal public-facing settings (withdrawal days etc.) — no auth needed.
     */
    public function publicSettings(): JsonResponse
    {
        $keys = ['withdrawal_allowed_days', 'withdrawal_min_amount', 'withdrawal_processing_days', 'withdrawal_enabled'];
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = PlatformSetting::get($key);
        }
        return response()->json(['data' => $data]);
    }

    /**
     * PUT /admin/settings
     * Bulk update settings. Body: { key: value, ... }
     */
    public function update(Request $request): JsonResponse
    {
        $updates = $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable',
        ]);

        foreach ($updates['settings'] as $key => $value) {
            $setting = PlatformSetting::where('key', $key)->first();
            if (!$setting) continue;

            // Special handling for json type
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

    private function castValue(PlatformSetting $s): mixed
    {
        return match ($s->type) {
            'boolean' => filter_var($s->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $s->value,
            'json'    => json_decode($s->value, true),
            default   => $s->value,
        };
    }
}
