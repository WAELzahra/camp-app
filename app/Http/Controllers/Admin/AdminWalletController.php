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
        $query = AdminWalletTransaction::with('relatedUser:id,first_name,last_name,email')
            ->latest();

        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category', $request->category);
        }

        $txns = $query->paginate($request->get('per_page', 20));

        return response()->json(['success' => true, 'data' => $txns]);
    }
}
