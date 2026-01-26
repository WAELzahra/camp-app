<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceCategoryController extends Controller
{
    /**
     * Display a listing of service categories.
     */
    public function index()
    {
        $serviceCategories = ServiceCategory::orderBy('sort_order')->orderBy('name')->get();
        
        return view('admin.service-categories.index', compact('serviceCategories'));
    }

    /**
     * Show the form for creating a new service category.
     */
    public function create()
    {
        return view('admin.service-categories.create');
    }

    /**
     * Store a newly created service category.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:service_categories',
            'description' => 'nullable|string',
            'is_standard' => 'boolean',
            'suggested_price' => 'required|numeric|min:0',
            'min_price' => 'required|numeric|min:0|lte:suggested_price',
            'unit' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        ServiceCategory::create($validated);

        return redirect()->route('admin.service-categories.index')
            ->with('success', 'Service category created successfully.');
    }

    /**
     * Display the specified service category.
     */
    public function show(ServiceCategory $serviceCategory)
    {
        return view('admin.service-categories.show', compact('serviceCategory'));
    }

    /**
     * Show the form for editing the specified service category.
     */
    public function edit(ServiceCategory $serviceCategory)
    {
        return view('admin.service-categories.edit', compact('serviceCategory'));
    }

    /**
     * Update the specified service category.
     */
    public function update(Request $request, ServiceCategory $serviceCategory)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('service_categories')->ignore($serviceCategory->id)
            ],
            'description' => 'nullable|string',
            'is_standard' => 'boolean',
            'suggested_price' => 'required|numeric|min:0',
            'min_price' => 'required|numeric|min:0|lte:suggested_price',
            'unit' => 'required|string|max:50',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'integer|min:0',
            'is_active' => 'boolean',
        ]);

        $serviceCategory->update($validated);

        return redirect()->route('admin.service-categories.index')
            ->with('success', 'Service category updated successfully.');
    }

    /**
     * Remove the specified service category.
     */
    public function destroy(ServiceCategory $serviceCategory)
    {
        // Check if service is used by any centers
        if ($serviceCategory->centerServices()->count() > 0) {
            return redirect()->route('admin.service-categories.index')
                ->with('error', 'Cannot delete service category that is being used by centers.');
        }

        $serviceCategory->delete();

        return redirect()->route('admin.service-categories.index')
            ->with('success', 'Service category deleted successfully.');
    }

    /**
     * Toggle active status
     */
    public function toggleActive(ServiceCategory $serviceCategory)
    {
        $serviceCategory->update([
            'is_active' => !$serviceCategory->is_active
        ]);

        return back()->with('success', 'Service category status updated.');
    }

    /**
     * Get service categories for API (for center forms, etc.)
     */
    public function apiIndex()
    {
        $serviceCategories = ServiceCategory::active()
            ->ordered()
            ->get()
            ->map(function ($category) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'is_standard' => $category->is_standard,
                    'suggested_price' => $category->suggested_price,
                    'min_price' => $category->min_price,
                    'unit' => $category->unit,
                    'icon' => $category->icon,
                ];
            });

        return response()->json($serviceCategories);
    }
}