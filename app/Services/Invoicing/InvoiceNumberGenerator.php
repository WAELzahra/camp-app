<?php

namespace App\Services\Invoicing;

use Illuminate\Support\Facades\DB;

/**
 * Task A-03 — gap-free, sequential, irreversible invoice numbering.
 * Format: TC-YYYY-XXXXXX. Uses a per-year counter row locked FOR UPDATE
 * inside the caller's transaction so concurrent requests cannot collide
 * or leave gaps.
 */
class InvoiceNumberGenerator
{
    /**
     * MUST be called inside a DB transaction (the row lock is what makes
     * the sequence race-safe).
     */
    public static function next(): string
    {
        $year = (int) now()->format('Y');

        // Ensure the counter row exists (no-op when already present).
        DB::table('invoice_sequences')->insertOrIgnore([
            'year' => $year, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $row = DB::table('invoice_sequences')->where('year', $year)->lockForUpdate()->first();

        $next = $row->last_number + 1;
        DB::table('invoice_sequences')->where('year', $year)->update([
            'last_number' => $next,
            'updated_at'  => now(),
        ]);

        return sprintf('TC-%d-%06d', $year, $next);
    }
}
