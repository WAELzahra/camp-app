<?php

namespace App\Http\Controllers\Center;

use App\Http\Controllers\Controller;
use App\Models\ProfileCentre;
use App\Models\ServiceCategory;
use App\Models\ProfileCenterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CenterServiceController extends Controller
{
    /**
     * Display services for a center
     */
    public function index($centerId)
    {
        $center = ProfileCentre::with(['services', 'equipment'])->findOrFail($centerId);
        
        // Check authorization
        $this->authorize('manage', $center);
        
        $serviceCategories = ServiceCategory::active()->ordered()->get();
        
        return view('center.services.index', compact('center', 'serviceCategories'));
    }

    /**
     * Add/Update service for a center
     */
    public function store(Request $request, $centerId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $validated = $request->validate([
            'service_category_id' => 'required|exists:service_categories,id',
            'price' => 'required|numeric|min:0',
            'unit' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'is_available' => 'boolean',
            'is_standard' => 'boolean',
            'min_quantity' => 'integer|min:1',
            'max_quantity' => 'nullable|integer|min:1',
        ]);

        // Check if service already exists for this center
        $existingService = $center->centerServices()
            ->where('service_category_id', $validated['service_category_id'])
            ->first();

        if ($existingService) {
            // Update existing service
            $existingService->update($validated);
            $message = 'Service updated successfully.';
        } else {
            // Create new service
            $center->addService(
                ServiceCategory::find($validated['service_category_id']),
                $validated['price'],
                $validated
            );
            $message = 'Service added successfully.';
        }

        // Update services_offerts field
        $center->updateServicesOfferts();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'center' => $center->fresh(['services', 'equipment'])
            ]);
        }

        return redirect()->route('center.services.index', $center->id)
            ->with('success', $message);
    }

    /**
     * Update service availability
     */
    public function updateAvailability(Request $request, $centerId, $serviceId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $service = ProfileCenterService::where('profile_center_id', $centerId)
            ->where('id', $serviceId)
            ->firstOrFail();

        $validated = $request->validate([
            'is_available' => 'required|boolean',
            'price' => 'nullable|numeric|min:0',
        ]);

        $updateData = ['is_available' => $validated['is_available']];
        
        if (isset($validated['price'])) {
            $updateData['price'] = $validated['price'];
        }

        $service->update($updateData);
        
        // Update services_offerts
        $center->updateServicesOfferts();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service availability updated.',
                'service' => $service->fresh()
            ]);
        }

        return back()->with('success', 'Service availability updated.');
    }

    /**
     * Remove service from center
     */
    public function destroy($centerId, $serviceId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $service = ProfileCenterService::where('profile_center_id', $centerId)
            ->where('id', $serviceId)
            ->firstOrFail();

        // Don't allow deletion of standard service
        if ($service->is_standard) {
            return back()->with('error', 'Cannot remove standard service.');
        }

        $service->delete();
        
        // Update services_offerts
        $center->updateServicesOfferts();

        if (request()->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Service removed from center.'
            ]);
        }

        return back()->with('success', 'Service removed from center.');
    }

    /**
     * Bulk update center services
     */
    public function bulkUpdate(Request $request, $centerId)
    {
        $center = ProfileCentre::findOrFail($centerId);
        $this->authorize('manage', $center);

        $validated = $request->validate([
            'services' => 'required|array',
            'services.*.service_category_id' => 'required|exists:service_categories,id',
            'services.*.price' => 'required|numeric|min:0',
            'services.*.is_available' => 'boolean',
            'services.*.is_standard' => 'boolean',
        ]);

        $center->syncServices($validated['services']);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Services updated successfully.',
                'center' => $center->fresh(['services', 'equipment'])
            ]);
        }

        return redirect()->route('center.services.index', $center->id)
            ->with('success', 'Services updated successfully.');
    }

    /**
     * Get center services for API
     */
    public function apiIndex($centerId)
    {
        $center = ProfileCentre::with(['services', 'equipment'])->findOrFail($centerId);
        
        $services = $center->availableServices()->get()->map(function ($service) {
            return [
                'id' => $service->id,
                'name' => $service->name,
                'description' => $service->description,
                'price' => $service->pivot->price,
                'unit' => $service->pivot->unit,
                'is_standard' => $service->pivot->is_standard,
                'min_quantity' => $service->pivot->min_quantity,
                'max_quantity' => $service->pivot->max_quantity,
            ];
        });

        $equipment = $center->availableEquipment()->get()->map(function ($eq) {
            return [
                'type' => $eq->type,
                'translated_type' => $eq->translated_type,
                'icon' => $eq->icon,
            ];
        });

        return response()->json([
            'services' => $services,
            'equipment' => $equipment,
            'center' => [
                'name' => $center->name,
                'price_per_night' => $center->price_per_night,
                'category' => $center->category,
            ]
        ]);
    }
}