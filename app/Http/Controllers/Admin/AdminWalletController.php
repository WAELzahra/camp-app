<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminWalletTransaction;
use App\Models\Reservations_centre;
use App\Models\Reservations_events;
use App\Models\Reservations_materielles;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminWalletController extends Controller
{
    public function summary(Request $request)
    {
        // ── Commissions earned (from wallet_transactions) ──────────────────────
        $totalCommissions = WalletTransaction::where('category', 'reservation_income')
            ->where('commission_amount', '>', 0)
            ->sum('commission_amount');

        // ── Platform service fees earned (from reservation tables) ─────────────
        // Centre: approved reservations
        $centreFees = Reservations_centre::whereIn('status', ['approved'])
            ->whereNotNull('platform_fee_amount')
            ->sum('platform_fee_amount');

        // Events: confirmed reservations
        $eventFees = Reservations_events::whereIn('status', ['confirmée'])
            ->whereNotNull('platform_fee_amount')
            ->sum('platform_fee_amount');

        // Equipment: confirmed + returned reservations
        $materielFees = Reservations_materielles::whereIn('status', ['confirmed', 'returned'])
            ->whereNotNull('platform_fee_amount')
            ->sum('platform_fee_amount');

        $totalPlatformFees = round($centreFees + $eventFees + $materielFees, 2);

        // ── Platform cancellation fees (from admin_wallet_transactions) ────────
        $totalCancellationFees = AdminWalletTransaction::where('category', 'platform_cancellation_fee')
            ->sum('amount');

        $grandTotal = round($totalCommissions + $totalPlatformFees + $totalCancellationFees, 2);

        // ── Category breakdown from AdminWalletTransaction ─────────────────────
        $breakdown = AdminWalletTransaction::select('category', DB::raw('SUM(amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        // ── Recent transactions ────────────────────────────────────────────────
        $recent = AdminWalletTransaction::with('relatedUser:id,first_name,last_name')
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'summary' => [
                'total_commissions'        => round($totalCommissions, 2),
                'total_platform_fees'      => $totalPlatformFees,
                'total_cancellation_fees'  => round($totalCancellationFees, 2),
                'grand_total'              => $grandTotal,
            ],
            'breakdown' => $breakdown,
            'recent'    => $recent,
        ]);
    }

    public function transactions(Request $request)
    {
        $perPage  = (int) $request->get('per_page', 20);
        $page     = (int) $request->get('page', 1);
        $category = $request->filled('category') && $request->category !== 'all' ? $request->category : null;
        $userId   = $request->filled('user_id') ? (int) $request->user_id : null;

        // ── AdminWalletTransaction records (platform_fee, cancellation_fee, etc.) ──
        $adminRows = AdminWalletTransaction::with('relatedUser:id,first_name,last_name,email')
            ->when($category, fn($q) => $q->where('category', $category))
            ->when($userId,   fn($q) => $q->where('related_user_id', $userId))
            ->get()
            ->map(fn($t) => [
                'id'             => 'a-' . $t->id,
                'category'       => $t->category,
                'amount'         => (float) $t->amount,
                'reference_type' => $t->reference_type,
                'reference_id'   => $t->reference_id,
                'description'    => $t->description,
                'created_at'     => $t->created_at,
                'related_user'   => $t->relatedUser ? [
                    'id'         => $t->relatedUser->id,
                    'first_name' => $t->relatedUser->first_name,
                    'last_name'  => $t->relatedUser->last_name,
                    'email'      => $t->relatedUser->email,
                ] : null,
                'source'         => 'admin_wallet',
            ]);

        // ── Commission records from WalletTransaction (covers all historical data) ──
        // Only include if no category filter, or filtering by 'commission'
        $commRows = collect();
        if (!$category || $category === 'commission') {
            // Get reference_ids already covered by AdminWalletTransaction commission records
            $coveredIds = AdminWalletTransaction::where('category', 'commission')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->toArray();

            $commRows = WalletTransaction::with([
                    'user:id,first_name,last_name,email',
                    'relatedUser:id,first_name,last_name,email',
                ])
                ->where('type', 'credit')
                ->where('commission_amount', '>', 0)
                ->when(!empty($coveredIds), fn($q) => $q->whereNotIn('reference_id', $coveredIds))
                ->when($userId, fn($q) => $q->where('user_id', $userId))
                ->get()
                ->map(fn($t) => [
                    'id'             => 'w-' . $t->id,
                    'category'       => 'commission',
                    'amount'         => (float) $t->commission_amount,
                    'reference_type' => $t->reference_type,
                    'reference_id'   => $t->reference_id,
                    'description'    => "Commission — {$t->description}",
                    'created_at'     => $t->created_at,
                    'related_user'   => $t->user ? [
                        'id'         => $t->user->id,
                        'first_name' => $t->user->first_name,
                        'last_name'  => $t->user->last_name,
                        'email'      => $t->user->email,
                    ] : null,
                    'source'         => 'wallet_transaction',
                ]);
        }

        // ── Platform fee records from WalletTransaction (camper's debit — covers old data) ──
        $feeRows = collect();
        if (!$category || $category === 'platform_fee') {
            $coveredFeeIds = AdminWalletTransaction::where('category', 'platform_fee')
                ->pluck('reference_id')
                ->filter()
                ->unique()
                ->toArray();

            $feeRows = WalletTransaction::with('relatedUser:id,first_name,last_name,email')
                ->where('type', 'debit')
                ->where('category', 'reservation_payment')
                ->where('commission_amount', '>', 0)
                ->when(!empty($coveredFeeIds), fn($q) => $q->whereNotIn('reference_id', $coveredFeeIds))
                ->when($userId, fn($q) => $q->where('related_user_id', $userId))
                ->get()
                ->map(fn($t) => [
                    'id'             => 'f-' . $t->id,
                    'category'       => 'platform_fee',
                    'amount'         => (float) $t->commission_amount,
                    'reference_type' => $t->reference_type,
                    'reference_id'   => $t->reference_id,
                    'description'    => "Platform fee — {$t->description}",
                    'created_at'     => $t->created_at,
                    'related_user'   => $t->relatedUser ? [
                        'id'         => $t->relatedUser->id,
                        'first_name' => $t->relatedUser->first_name,
                        'last_name'  => $t->relatedUser->last_name,
                        'email'      => $t->relatedUser->email,
                    ] : null,
                    'source'         => 'wallet_transaction',
                ]);
        }

        // ── Combine, sort, paginate ────────────────────────────────────────────
        $all         = $adminRows->concat($commRows)->concat($feeRows)
                                 ->sortByDesc('created_at')->values();
        $total       = $all->count();
        $totalAmount = round($all->sum('amount'), 2);
        $items       = $all->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'data'         => $items,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $perPage) ?: 1,
                'per_page'     => $perPage,
                'total'        => $total,
                'total_amount' => $totalAmount,
            ],
        ]);
    }
}
