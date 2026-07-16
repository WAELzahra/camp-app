<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Models\PartnerType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PartnerController extends Controller
{
    // GET /admin/partner-types
    public function types()
    {
        return response()->json(['partner_types' => PartnerType::orderBy('label')->get()]);
    }

    // POST /admin/partner-types
    public function storeType(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:partner_types,code',
            'label' => 'required|string|max:100',
            'icon' => 'nullable|string|max:50',
            'requires_platform_account' => 'boolean',
        ]);

        $type = PartnerType::create($validated);

        return response()->json(['partner_type' => $type], 201);
    }

    // GET /admin/partners
    public function index()
    {
        $partners = Partner::with(['partnerType', 'user:id,first_name,last_name,email'])
            ->orderBy('name')
            ->get();

        return response()->json(['partners' => $partners]);
    }

    // POST /admin/partners
    public function store(Request $request)
    {
        $validated = $request->validate([
            'partner_type_id' => 'required|exists:partner_types,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'required|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'default_commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,pending,suspended',
        ]);

        $partner = Partner::create($validated);

        return response()->json(['partner' => $partner->load('partnerType', 'user')], 201);
    }

    // GET /admin/partners/{id}
    public function show(int $id)
    {
        $partner = Partner::with(['partnerType', 'user:id,first_name,last_name,email', 'stepPartners.step.programme'])
            ->findOrFail($id);

        return response()->json(['partner' => $partner]);
    }

    // PUT /admin/partners/{id}
    public function update(Request $request, int $id)
    {
        $partner = Partner::findOrFail($id);

        $validated = $request->validate([
            'partner_type_id' => 'sometimes|exists:partner_types,id',
            'user_id' => 'nullable|exists:users,id',
            'name' => 'sometimes|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'default_commission_rate' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:active,pending,suspended',
        ]);

        $partner->update($validated);

        return response()->json(['partner' => $partner->load('partnerType', 'user')]);
    }

    // DELETE /admin/partners/{id}
    public function destroy(int $id)
    {
        $partner = Partner::findOrFail($id);

        if ($partner->stepPartners()->exists()) {
            return response()->json(['message' => 'Impossible de supprimer un partenaire assigné à un programme.'], 422);
        }

        $partner->delete();

        return response()->json(['message' => 'Partenaire supprimé.']);
    }

    // POST /admin/partners/{id}/signed-link — read-only link for partners without a platform account
    public function signedLink(int $id)
    {
        $partner = Partner::findOrFail($id);

        $url = URL::temporarySignedRoute(
            'programme.partner-view',
            now()->addDays(90),
            ['partner' => $partner->id]
        );

        return response()->json(['url' => $url, 'expires_in_days' => 90]);
    }
}
