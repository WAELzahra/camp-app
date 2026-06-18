<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddCommissionUserRequest;
use App\Http\Requests\Admin\SearchCommissionUsersRequest;
use App\Http\Requests\Admin\StoreCustomCommissionRequest;
use App\Http\Requests\Admin\UpdateCustomCommissionRequest;
use App\Models\CustomCommissionRule;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminCustomCommissionController extends Controller
{
    /* ─── Eligible role IDs: groupe (2), fournisseur (4), guide (5), centre (3) ─── */
    private const ELIGIBLE_ROLE_IDS = [2, 3, 4, 5];

    /**
     * GET /admin/custom-commissions
     * List all custom commission rules with their user count.
     */
    public function index(): JsonResponse
    {
        $rules = CustomCommissionRule::withCount('users')
            ->with(['users:id,first_name,last_name,email,avatar,role_id', 'users.role:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($rule) => $this->formatRule($rule));

        return response()->json(['data' => $rules]);
    }

    /**
     * POST /admin/custom-commissions
     * Create a new rule.
     */
    public function store(StoreCustomCommissionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $rule = CustomCommissionRule::create($data);
        $rule->load(['users:id,first_name,last_name,email,avatar,role_id', 'users.role:id,name']);
        $rule->loadCount('users');

        return response()->json(['data' => $this->formatRule($rule), 'message' => 'Règle créée avec succès.'], 201);
    }

    /**
     * GET /admin/custom-commissions/{id}
     * Get a single rule with full user details.
     */
    public function show(int $id): JsonResponse
    {
        $rule = CustomCommissionRule::with([
            'users:id,first_name,last_name,email,avatar,role_id',
            'users.role:id,name',
        ])->withCount('users')->findOrFail($id);

        return response()->json(['data' => $this->formatRule($rule)]);
    }

    /**
     * PUT /admin/custom-commissions/{id}
     * Update a rule's metadata.
     * If activating a previously inactive rule, warns about users that already have
     * another active rule (their assignment will follow first-match priority in that case).
     */
    public function update(UpdateCustomCommissionRequest $request, int $id): JsonResponse
    {
        $rule = CustomCommissionRule::findOrFail($id);

        $data = $request->validated();

        // When activating a rule, check for users that already have a different active rule.
        $conflicts = [];
        $activating = isset($data['is_active']) && $data['is_active'] && !$rule->is_active;
        if ($activating) {
            $userIdsInThisRule = $rule->users()->pluck('users.id');
            if ($userIdsInThisRule->isNotEmpty()) {
                $conflictingRules = CustomCommissionRule::where('is_active', true)
                    ->where('id', '!=', $id)
                    ->whereHas('users', fn ($q) => $q->whereIn('user_id', $userIdsInThisRule))
                    ->with(['users' => fn ($q) => $q->whereIn('users.id', $userIdsInThisRule)
                        ->select('users.id', 'first_name', 'last_name')])
                    ->get();

                foreach ($conflictingRules as $conflictRule) {
                    foreach ($conflictRule->users as $u) {
                        $conflicts[] = [
                            'user_id' => $u->id,
                            'user_name' => "{$u->first_name} {$u->last_name}",
                            'existing_rule' => $conflictRule->name,
                        ];
                    }
                }
            }
        }

        $rule->update($data);
        $rule->load(['users:id,first_name,last_name,email,avatar,role_id', 'users.role:id,name']);
        $rule->loadCount('users');

        $message = $activating && count($conflicts) > 0
            ? 'Règle mise à jour. Attention : '.count($conflicts).' utilisateur(s) ont déjà une autre règle active — leur taux sera celui de la règle la plus ancienne.'
            : 'Règle mise à jour.';

        return response()->json([
            'data' => $this->formatRule($rule),
            'message' => $message,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * DELETE /admin/custom-commissions/{id}
     * Delete a rule (cascade removes pivot rows).
     */
    public function destroy(int $id): JsonResponse
    {
        $rule = CustomCommissionRule::findOrFail($id);
        $rule->delete();

        return response()->json(['message' => 'Règle supprimée.']);
    }

    /**
     * POST /admin/custom-commissions/{id}/users
     * Add a user to a rule. Body: { user_id }
     */
    public function addUser(AddCommissionUserRequest $request, int $id): JsonResponse
    {
        $rule = CustomCommissionRule::findOrFail($id);

        $data = $request->validated();

        $user = User::findOrFail($data['user_id']);

        // Enforce eligible roles
        if (!in_array($user->role_id, self::ELIGIBLE_ROLE_IDS)) {
            return response()->json(['message' => 'Ce rôle utilisateur n\'est pas éligible à une commission personnalisée.'], 422);
        }

        // Check if user already has an active rule
        $existing = CustomCommissionRule::where('is_active', true)
            ->whereHas('users', fn ($q) => $q->where('user_id', $user->id))
            ->where('id', '!=', $id)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => "Cet utilisateur a déjà une règle active : « {$existing->name} ». Retirez-le de cette règle d'abord.",
            ], 422);
        }

        $rule->users()->syncWithoutDetaching([$user->id]);
        $rule->load(['users:id,first_name,last_name,email,avatar,role_id', 'users.role:id,name']);
        $rule->loadCount('users');

        return response()->json(['data' => $this->formatRule($rule), 'message' => 'Utilisateur ajouté à la règle.']);
    }

    /**
     * DELETE /admin/custom-commissions/{id}/users/{userId}
     * Remove a user from a rule.
     */
    public function removeUser(int $id, int $userId): JsonResponse
    {
        $rule = CustomCommissionRule::findOrFail($id);
        $rule->users()->detach($userId);
        $rule->load(['users:id,first_name,last_name,email,avatar,role_id', 'users.role:id,name']);
        $rule->loadCount('users');

        return response()->json(['data' => $this->formatRule($rule), 'message' => 'Utilisateur retiré de la règle.']);
    }

    /**
     * GET /admin/custom-commissions/users/search?q=&role_id=&exclude_rule_id=
     * Search eligible users by name/email, optionally filtered by role.
     */
    public function searchUsers(SearchCommissionUsersRequest $request): JsonResponse
    {

        $q = $request->input('q', '');
        $roleId = $request->input('role_id');

        $query = User::with('role:id,name')
            ->select('id', 'first_name', 'last_name', 'email', 'avatar', 'role_id')
            ->whereIn('role_id', self::ELIGIBLE_ROLE_IDS);

        if ($roleId) {
            $query->where('role_id', $roleId);
        }

        if ($q) {
            $query->where(function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$q}%"]);
            });
        }

        $users = $query->orderBy('first_name')->limit(20)->get();

        // For each user, check if they already have an active custom commission rule.
        // This lets the frontend show a warning before the admin tries to add them.
        $userIds = $users->pluck('id')->toArray();
        $activeRulesByUser = [];
        if (!empty($userIds)) {
            $rulesWithUsers = CustomCommissionRule::where('is_active', true)
                ->whereHas('users', fn ($q) => $q->whereIn('user_id', $userIds))
                ->with(['users' => fn ($q) => $q->whereIn('users.id', $userIds)->select('users.id')])
                ->get(['id', 'name']);

            foreach ($rulesWithUsers as $r) {
                foreach ($r->users as $u) {
                    $activeRulesByUser[$u->id] = ['rule_id' => $r->id, 'rule_name' => $r->name];
                }
            }
        }

        $mapped = $users->map(fn ($u) => [
            'id' => $u->id,
            'first_name' => $u->first_name,
            'last_name' => $u->last_name,
            'email' => $u->email,
            'avatar' => $u->avatar,
            'role_id' => $u->role_id,
            'role_name' => $u->role?->name ?? 'unknown',
            'active_rule' => $activeRulesByUser[$u->id] ?? null, // null = no custom rule assigned
        ]);

        return response()->json(['data' => $mapped]);
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    private function formatRule(CustomCommissionRule $rule): array
    {
        return [
            'id' => $rule->id,
            'name' => $rule->name,
            'description' => $rule->description,
            'commission_rate' => $rule->commission_rate,
            'is_active' => $rule->is_active,
            'users_count' => $rule->users_count ?? $rule->users->count(),
            'users' => $rule->relationLoaded('users')
                ? $rule->users->map(fn ($u) => [
                    'id' => $u->id,
                    'first_name' => $u->first_name,
                    'last_name' => $u->last_name,
                    'email' => $u->email,
                    'avatar' => $u->avatar,
                    'role_id' => $u->role_id,
                    'role_name' => $u->role?->name ?? 'unknown',
                ])
                : [],
            'created_at' => $rule->created_at?->toIso8601String(),
            'updated_at' => $rule->updated_at?->toIso8601String(),
        ];
    }
}
