<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payments\ClicToPayRecetteRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Browser front-end for ClicToPayRecetteRunner (admin-only).
 *
 * The cahier de recettes asks for evidence produced by the merchant site itself
 * rather than by a REST client. Hitting this URL from a logged-in admin session
 * makes the CTP-06/07/08 calls originate from a real HTTP request to the site,
 * and returns ClicToPay's untouched JSON so it can be copied straight into
 * column G. Identical output to `php artisan clictopay:recette`.
 *
 * Remove this route once the cahier is submitted — it exists only to generate
 * error cases the production flow deliberately makes unreachable.
 */
class ClicToPayRecetteController extends Controller
{
    public function __construct(private ClicToPayRecetteRunner $runner)
    {
    }

    /** GET /api/admin/clictopay/recette?case=all|06|07|08&order_number=ORDER-DUP-TEST */
    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'case'         => 'nullable|in:' . implode(',', ClicToPayRecetteRunner::CASES),
            // AN..32 per the manual — a longer orderNumber is rejected by register.do.
            'order_number' => 'nullable|string|max:32',
        ]);

        $results = $this->runner->run(
            $data['case'] ?? 'all',
            $data['order_number'] ?? ClicToPayRecetteRunner::DEFAULT_DUP_ORDER_NUMBER,
        );

        return response()->json([
            'base_url'  => rtrim((string) config('services.clictopay.base_url'), '/') . '/',
            'executed_at' => now()->toIso8601String(),
            'cases'     => $results,
        ], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
