<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProfileCentre;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;

class CenterServiceApiController extends Controller
{
    /**
     * Get all centers with their services
     */
    public function centersWithServices(Request $request)
    {
        $query = ProfileCentre::with(['availableServices', 'availableEquipment'])
            ->available();

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by equipment
        if ($request->has('equipment')) {
            $query->withEquipment($request->equipment);
        }

        // Filter by service
        if ($request->has('service')) {
            $query->withService($request->service);
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        $centers = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $centers->items(),
            'meta' => [
                'current_page' => $centers->currentPage(),
                'last_page' => $centers->lastPage(),
                'per_page' => $centers->perPage(),
                'total' => $centers->total(),
            ]
        ]);
    }

    /**
     * Get services for a specific center
     */
    public function centerServices($centerId)
    {
        $center = ProfileCentre::with(['availableServices', 'availableEquipment'])
            ->available()
            ->findOrFail($centerId);

        return response()->json([
            'center' => [
                'id' => $center->id,
                'name' => $center->name,
                'price_per_night' => $center->price_per_night,
                'category' => $center->category,
                'capacity' => $center->capacite,
                'location' => $center->coordinates,
            ],
            'services' => $center->availableServices->map(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => $service->pivot->price,
                    'unit' => $service->pivot->unit,
                    'is_standard' => $service->pivot->is_standard,
                ];
            }),
            'equipment' => $center->availableEquipment->map(function ($eq) {
                return [
                    'type' => $eq->type,
                    'name' => $eq->translated_type,
                    'icon' => $eq->icon,
                ];
            }),
        ]);
    }

    /**
     * Get all available service categories
     */
    public function serviceCategories()
    {
        $categories = ServiceCategory::active()
            ->ordered()
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'is_standard' => $category->is_standard,
                    'suggested_price' => (float) $category->suggested_price,
                    'min_price' => (float) $category->min_price,
                    'unit' => $category->unit,
                    'icon' => $category->icon,
                    'sort_order' => $category->sort_order,
                    'is_active' => $category->is_active,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at,
                ];
            });

        return response()->json($categories);
    }

    /**
     * Calculate total price for booking
     */
    public function calculatePrice(Request $request)
    {
        $validated = $request->validate([
            'center_id' => 'required|exists:profile_centres,id',
            'nights' => 'required|integer|min:1',
            'people' => 'required|integer|min:1',
            'services' => 'array',
            'services.*.service_id' => 'required|exists:service_categories,id',
            'services.*.quantity' => 'required|integer|min:1',
        ]);

        $center = ProfileCentre::with('services')->findOrFail($validated['center_id']);

        $total = 0;
        $breakdown = [];

        // Standard camping price
        $standardService = $center->standardService();
        if ($standardService) {
            $standardPrice = $standardService->pivot->price * $validated['nights'] * $validated['people'];
            $total += $standardPrice;
            $breakdown[] = [
                'name' => $standardService->name,
                'price' => $standardService->pivot->price,
                'nights' => $validated['nights'],
                'people' => $validated['people'],
                'subtotal' => $standardPrice,
            ];
        }

        // Additional services
        foreach ($validated['services'] ?? [] as $requestedService) {
            $service = $center->services()
                ->where('service_categories.id', $requestedService['service_id'])
                ->where('profile_center_services.is_available', true)
                ->first();

            if ($service) {
                $servicePrice = $service->pivot->price * $requestedService['quantity'];
                $total += $servicePrice;
                $breakdown[] = [
                    'name' => $service->name,
                    'price' => $service->pivot->price,
                    'quantity' => $requestedService['quantity'],
                    'unit' => $service->pivot->unit,
                    'subtotal' => $servicePrice,
                ];
            }
        }

        return response()->json([
            'total' => round($total, 2),
            'breakdown' => $breakdown,
            'currency' => 'TND',
        ]);
    }
}