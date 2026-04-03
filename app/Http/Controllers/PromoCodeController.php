<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC — validate a promo code (called by booking forms)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * POST /promo-code/validate
     * Body: { code, reservation_type, price }
     */
    public function validate(Request $request)
    {
        $request->validate([
            'code'             => 'required|string|max:50',
            'reservation_type' => 'required|in:centre,materiel,event',
            'price'            => 'required|numeric|min:0',
        ]);

        $promo = PromoCode::where('code', strtoupper(trim($request->code)))->first();

        if (!$promo) {
            return response()->json([
                'valid'   => false,
                'message' => 'Invalid promo code.',
            ], 422);
        }

        $check = $promo->isValid($request->reservation_type, (float) $request->price);

        if (!$check['valid']) {
            return response()->json([
                'valid'   => false,
                'message' => $check['reason'],
            ], 422);
        }

        $discount      = $promo->calculateDiscount((float) $request->price);
        $finalPrice    = max(0, round((float) $request->price - $discount, 2));

        return response()->json([
            'valid'          => true,
            'message'        => 'Promo code applied successfully!',
            'promo_code_id'  => $promo->id,
            'code'           => $promo->code,
            'discount_type'  => $promo->discount_type,
            'discount_value' => $promo->discount_value,
            'discount_amount'=> $discount,
            'original_price' => (float) $request->price,
            'final_price'    => $finalPrice,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ADMIN CRUD
    // ─────────────────────────────────────────────────────────────────────────

    /** GET /admin/promo-codes */
    public function index(Request $request)
    {
        $query = PromoCode::query();

        if ($request->filled('search')) {
            $query->where('code', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', (bool) $request->is_active);
        }

        if ($request->filled('applicable_to')) {
            $query->where('applicable_to', $request->applicable_to);
        }

        $promoCodes = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($promoCodes);
    }

    /** POST /admin/promo-codes */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'           => 'required|string|max:50|unique:promo_codes,code',
            'discount_type'  => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0.01',
            'applicable_to'  => 'required|in:all,centre,materiel,event',
            'min_price'      => 'nullable|numeric|min:0',
            'max_uses'       => 'nullable|integer|min:1',
            'is_active'      => 'boolean',
            'expires_in_days'=> 'nullable|integer|min:1|max:3650',
        ]);

        // Validate percentage does not exceed 100
        if ($validated['discount_type'] === 'percentage' && $validated['discount_value'] > 100) {
            return response()->json(['message' => 'Percentage discount cannot exceed 100%.'], 422);
        }

        $expiresAt = null;
        if (!empty($validated['expires_in_days'])) {
            $expiresAt = Carbon::now()->addDays($validated['expires_in_days']);
        }

        $promo = PromoCode::create([
            'code'           => strtoupper(trim($validated['code'])),
            'discount_type'  => $validated['discount_type'],
            'discount_value' => $validated['discount_value'],
            'applicable_to'  => $validated['applicable_to'],
            'min_price'      => $validated['min_price'] ?? null,
            'max_uses'       => $validated['max_uses'] ?? null,
            'is_active'      => $validated['is_active'] ?? true,
            'expires_at'     => $expiresAt,
        ]);

        return response()->json([
            'message'    => 'Promo code created successfully.',
            'promo_code' => $promo,
        ], 201);
    }

    /** GET /admin/promo-codes/{id} */
    public function show(int $id)
    {
        $promo = PromoCode::findOrFail($id);
        return response()->json($promo);
    }

    /** PATCH /admin/promo-codes/{id}/toggle */
    public function toggle(int $id)
    {
        $promo = PromoCode::findOrFail($id);
        $promo->update(['is_active' => !$promo->is_active]);

        return response()->json([
            'message'   => 'Promo code ' . ($promo->is_active ? 'enabled' : 'disabled') . '.',
            'is_active' => $promo->is_active,
        ]);
    }

    /** DELETE /admin/promo-codes/{id} */
    public function destroy(int $id)
    {
        $promo = PromoCode::findOrFail($id);
        $promo->delete();

        return response()->json(['message' => 'Promo code deleted successfully.']);
    }
}
