<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Persists a structured conversation state per user across turns.
 *
 * The state is the single source of truth for all user-supplied slots
 * (destination, group size, accommodation preference, etc.). The conversation
 * history passed to the LLM is context only — PHP never re-derives these
 * values by scanning raw message text.
 *
 * TTL is 30 minutes of inactivity. Any turn that sets is_new_trip=true
 * wipes the slate before applying the delta.
 *
 * All public methods are non-throwing: any cache failure is logged and a
 * safe default is returned so the planner always continues.
 */
class ConversationStateService
{
    private const TTL        = 1800;         // 30 min inactivity → reset
    private const KEY_PREFIX = 'conv_state:';

    // ── Public API ────────────────────────────────────────────────────────────

    public function load(int $userId): array
    {
        try {
            $state = Cache::get(self::KEY_PREFIX . $userId);
            return is_array($state) ? $state : $this->defaultState();
        } catch (\Throwable $e) {
            Log::warning('conv_state_load_failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return $this->defaultState();
        }
    }

    /**
     * Apply a delta to the persisted state and save it.
     *
     * If delta['is_new_trip'] === true the state is reset to defaults first.
     * On new-trip ONLY the destination carries over from this turn; all other
     * Group A slots reset to null so the modal re-collects them for the new trip.
     * This prevents IntentExtractor default values (group_size=2, nights=2) from
     * contaminating the fresh state and skipping the Group A collection step.
     *
     * For normal turns: null values in the delta are ignored so they never
     * overwrite a previously stored value.
     */
    public function merge(int $userId, array $delta): array
    {
        try {
            $state = $this->load($userId);

            if (($delta['is_new_trip'] ?? false) === true) {
                $state = $this->defaultState();
                // Only the destination from this turn carries over.
                if (array_key_exists('destination', $delta) && $delta['destination'] !== null) {
                    $state['destination'] = $delta['destination'];
                }
                $this->save($userId, $state);
                return $state;
            }

            $slots = [
                'destination',
                'group_size',
                'duration_nights',
                'accommodation_type',
                'wants_gear',
                'partner_choice',
            ];

            foreach ($slots as $slot) {
                if (array_key_exists($slot, $delta) && $delta[$slot] !== null) {
                    $state[$slot] = $delta[$slot];
                }
            }

            $this->save($userId, $state);
            return $state;
        } catch (\Throwable $e) {
            Log::warning('conv_state_merge_failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return $this->defaultState();
        }
    }

    public function save(int $userId, array $state): void
    {
        try {
            Cache::put(self::KEY_PREFIX . $userId, $state, self::TTL);
        } catch (\Throwable $e) {
            Log::warning('conv_state_save_failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    public function reset(int $userId): void
    {
        try {
            Cache::forget(self::KEY_PREFIX . $userId);
        } catch (\Throwable $e) {
            Log::warning('conv_state_reset_failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Returns true when the minimum required slot (destination) is filled.
     * A complete state is not yet sufficient to plan — accommodation type
     * must also be zone or centre — but it is sufficient to stop asking
     * "where in Tunisia?".
     */
    public function isComplete(array $state): bool
    {
        return $state['destination'] !== null;
    }

    /**
     * Returns true only when every Group A field has been explicitly confirmed:
     * destination, duration_nights, group_size, accommodation_type (zone|centre),
     * and wants_gear (true or false, never null).
     *
     * The pipeline must not run until this returns true.
     */
    public function isGroupAComplete(array $state): bool
    {
        return $state['destination'] !== null
            && $state['duration_nights'] !== null
            && $state['group_size'] !== null
            && in_array($state['accommodation_type'] ?? 'any', ['zone', 'centre'], true)
            && $state['wants_gear'] !== null;
    }

    /**
     * Returns field names that are null (or at their unspecified default) but
     * are required before a trip plan can be generated.
     * wants_gear is intentionally excluded — it is always asked via UI chips.
     */
    public function getMissingGroupAFields(array $state): array
    {
        $missing = [];

        foreach (['destination', 'group_size', 'duration_nights'] as $field) {
            if ($state[$field] === null) {
                $missing[] = $field;
            }
        }

        // accommodation_type must be an explicit choice (zone or centre), not the "any" default.
        if (! in_array($state['accommodation_type'] ?? 'any', ['zone', 'centre'], true)) {
            $missing[] = 'accommodation_type';
        }

        return $missing;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function defaultState(): array
    {
        return [
            'destination'        => null,
            'group_size'         => null,
            'duration_nights'    => null,
            'accommodation_type' => 'any',   // "any" = not yet chosen
            'wants_gear'         => null,    // null = not yet asked
            'partner_choice'     => null,
        ];
    }
}
