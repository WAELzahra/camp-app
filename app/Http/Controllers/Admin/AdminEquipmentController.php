<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProfileCenterEquipment;
use Illuminate\Http\Request;

class AdminEquipmentController extends Controller
{
    public function index(Request $request)
    {
        $query = ProfileCenterEquipment::with(['profileCenter'])
            ->orderBy('profile_center_id')
            ->orderBy('type');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_available') && $request->is_available !== '') {
            $query->where('is_available', filter_var($request->is_available, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('center_id')) {
            $query->where('profile_center_id', $request->center_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('type',  'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('profileCenter', fn($q2) =>
                      $q2->where('name', 'like', "%{$search}%")
                         ->orWhere('manager_name', 'like', "%{$search}%")
                  );
            });
        }

        $perPage   = min((int) $request->get('per_page', 50), 200);
        $equipment = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $equipment,
        ]);
    }

    public function toggleAvailability($id)
    {
        $eq = ProfileCenterEquipment::with('profileCenter')->findOrFail($id);
        $eq->is_available = !$eq->is_available;
        $eq->save();

        return response()->json(['success' => true, 'data' => $eq]);
    }

    public function update(Request $request, $id)
    {
        $eq = ProfileCenterEquipment::findOrFail($id);

        $request->validate([
            'is_available' => 'sometimes|boolean',
            'notes'        => 'sometimes|nullable|string|max:500',
        ]);

        $eq->update($request->only(['is_available', 'notes']));

        return response()->json(['success' => true, 'data' => $eq->fresh('profileCenter')]);
    }

    public function destroy($id)
    {
        ProfileCenterEquipment::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Equipment deleted.']);
    }

    public function bulkToggle(Request $request)
    {
        $request->validate([
            'ids'          => 'required|array|min:1',
            'ids.*'        => 'integer',
            'is_available' => 'required|boolean',
        ]);

        ProfileCenterEquipment::whereIn('id', $request->ids)
            ->update(['is_available' => $request->is_available]);

        return response()->json([
            'success' => true,
            'message' => count($request->ids) . ' equipment item(s) updated.',
        ]);
    }

    /** Summary counts grouped by type — for the management page stats bar */
    public function stats()
    {
        $total     = ProfileCenterEquipment::count();
        $available = ProfileCenterEquipment::where('is_available', true)->count();

        $byType = ProfileCenterEquipment::selectRaw('type, count(*) as total, sum(is_available) as available_count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        return response()->json([
            'success' => true,
            'data'    => [
                'total'     => $total,
                'available' => $available,
                'offline'   => $total - $available,
                'by_type'   => $byType,
            ],
        ]);
    }
}
