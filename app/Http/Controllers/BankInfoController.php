<?php

namespace App\Http\Controllers;

use App\Models\UserBankInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A user's own payout bank details (used to pay them out on withdrawal).
 * Available to every authenticated role; admins can list all of them.
 */
class BankInfoController extends Controller
{
    private const EMPTY = [
        'bank_name'      => null,
        'account_holder' => null,
        'iban'           => null,
        'flouci_phone'   => null,
        'card_last4'     => null,
    ];

    /** GET /my/bank-info — current user's saved payout details (empty shape if none). */
    public function show(): JsonResponse
    {
        $info = UserBankInfo::where('user_id', Auth::id())->first();

        return response()->json([
            'data' => $info
                ? $info->only(array_keys(self::EMPTY))
                : self::EMPTY,
        ]);
    }

    /** PUT /my/bank-info — create or update the current user's payout details. */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bank_name'      => 'nullable|string|max:255',
            'account_holder' => 'nullable|string|max:255',
            'iban'           => 'nullable|string|max:60',
            'flouci_phone'   => 'nullable|string|max:30',
            'card_last4'     => 'nullable|string|max:4',
        ]);

        $info = UserBankInfo::updateOrCreate(
            ['user_id' => Auth::id()],
            $data,
        );

        return response()->json([
            'message' => 'Informations bancaires enregistrées.',
            'data'    => $info->only(array_keys(self::EMPTY)),
        ]);
    }

    /**
     * GET /admin/bank-infos — list every user's payout details (admin only).
     * Optional ?search= filters by user name / email / IBAN.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $query = UserBankInfo::with(['user:id,first_name,last_name,email,role_id'])
            ->latest('updated_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('iban', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('account_holder', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($u) use ($search) {
                      $u->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        return response()->json($query->paginate(20));
    }
}
