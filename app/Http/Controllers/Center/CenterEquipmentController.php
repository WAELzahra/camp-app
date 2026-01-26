<?php

namespace App\Http\Controllers\Center;

use App\Http\Controllers\Controller;
use App\Models\ProfileCentre;
use App\Models\ProfileCenterEquipment;
use Illuminate\Http\Request;

class CenterEquipmentController extends Controller
{
    /**
     * Display equipment for a center
     */
    public function index($centerId)
    {
        $center = ProfileCentre::with('equipment')->findOrFail($centerId);
        $this->authorize('manage', $center);

        $equipmentTypes = ProfileCenterEquipment::TYPE_TRANSLATIONS;
        
        return view('center.equipment.index', compact('center', 'equipmentTypes'));
    }

    /**
     * Add/Update equipment for a center
     */
    public function store(Request $request, $centerId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $validated = $request->validate([
            'type' => 'required|string|in:' . implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'is_available' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Check if equipment already exists
        $existingEquipment = $center->equipment()
            ->where('type', $validated['type'])
            ->first();

        if ($existingEquipment) {
            // Update existing equipment
            $existingEquipment->update($validated);
            $message = 'Equipment updated successfully.';
        } else {
            // Create new equipment
            $center->addEquipment(
                $validated['type'],
                $validated['is_available'] ?? true,
                $validated['notes'] ?? null
            );
            $message = 'Equipment added successfully.';
        }

        // Update services_offerts
        $center->updateServicesOfferts();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'equipment' => $center->equipment()->where('type', $validated['type'])->first()
            ]);
        }

        return redirect()->route('center.equipment.index', $center->id)
            ->with('success', $message);
    }

    /**
     * Toggle equipment availability
     */
    public function toggleAvailability(Request $request, $centerId, $equipmentId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $equipment = ProfileCenterEquipment::where('profile_center_id', $centerId)
            ->where('id', $equipmentId)
            ->firstOrFail();

        $equipment->update([
            'is_available' => !$equipment->is_available
        ]);

        // Update services_offerts
        $center->updateServicesOfferts();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Equipment availability updated.',
                'equipment' => $equipment->fresh()
            ]);
        }

        return back()->with('success', 'Equipment availability updated.');
    }

    /**
     * Remove equipment from center
     */
    public function destroy($centerId, $equipmentId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $equipment = ProfileCenterEquipment::where('profile_center_id', $centerId)
            ->where('id', $equipmentId)
            ->firstOrFail();

        $equipment->delete();
        
        // Update services_offerts
        $center->updateServicesOfferts();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Equipment removed from center.'
            ]);
        }

        return back()->with('success', 'Equipment removed from center.');
    }

    /**
     * Bulk update equipment
     */
    public function bulkUpdate(Request $request, $centerId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $validated = $request->validate([
            'equipment' => 'required|array',
            'equipment.*.type' => 'required|string|in:' . implode(',', array_keys(ProfileCenterEquipment::TYPE_TRANSLATIONS)),
            'equipment.*.is_available' => 'boolean',
        ]);

        // Delete existing equipment
        $center->equipment()->delete();

        // Create new equipment entries
        foreach ($validated['equipment'] as $eq) {
            $center->addEquipment(
                $eq['type'],
                $eq['is_available'] ?? true
            );
        }

        // Update services_offerts
        $center->updateServicesOfferts();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Equipment updated successfully.',
                'center' => $center->fresh('equipment')
            ]);
        }

        return redirect()->route('center.equipment.index', $center->id)
            ->with('success', 'Equipment updated successfully.');
    }
}