<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Materielles;
use App\Models\Materielles_categories;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminMaterielleController extends Controller
{
    public function index(Request $request)
    {
        $query = Materielles::with(['fournisseur:id,first_name,last_name,email', 'category:id,nom', 'photos'])
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('nom',         'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhereHas('fournisseur', fn($q2) => $q2->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->filled('fournisseur_id')) {
            $query->where('fournisseur_id', $request->fournisseur_id);
        }

        if ($request->filled('type')) {
            if ($request->type === 'rentable')  $query->where('is_rentable', true);
            if ($request->type === 'sellable')  $query->where('is_sellable', true);
            if ($request->type === 'both')      $query->where('is_rentable', true)->where('is_sellable', true);
        }

        $perPage = min((int) $request->get('per_page', 20), 100);

        return response()->json([
            'success' => true,
            'data'    => $query->paginate($perPage),
        ]);
    }

    public function stats()
    {
        $total    = Materielles::count();
        $active   = Materielles::where('status', 'up')->count();
        $rentable = Materielles::where('is_rentable', true)->count();
        $sellable = Materielles::where('is_sellable', true)->count();

        $byCategory = Materielles::join('materielles_categories', 'materielles.category_id', '=', 'materielles_categories.id')
            ->selectRaw('materielles_categories.nom as category, count(*) as total, sum(materielles.status = "up") as active_count')
            ->groupBy('materielles_categories.nom')
            ->get();

        $categories = Materielles_categories::select('id', 'nom')->orderBy('nom')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'total'       => $total,
                'active'      => $active,
                'inactive'    => $total - $active,
                'rentable'    => $rentable,
                'sellable'    => $sellable,
                'by_category' => $byCategory,
                'categories'  => $categories,
            ],
        ]);
    }

    public function toggleStatus($id)
    {
        $mat = Materielles::findOrFail($id);
        $mat->status = $mat->status === 'up' ? 'down' : 'up';
        $mat->save();

        return response()->json(['success' => true, 'data' => $mat->load(['fournisseur:id,first_name,last_name,email', 'category:id,nom'])]);
    }

    public function update(Request $request, $id)
    {
        $mat = Materielles::findOrFail($id);

        $request->validate([
            'status'      => 'sometimes|in:up,down',
            'nom'         => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'tarif_nuit'  => 'sometimes|nullable|numeric|min:0',
            'prix_vente'  => 'sometimes|nullable|numeric|min:0',
            'is_rentable' => 'sometimes|boolean',
            'is_sellable' => 'sometimes|boolean',
            'quantite_dispo' => 'sometimes|integer|min:0',
        ]);

        $mat->update($request->only([
            'status', 'nom', 'description', 'tarif_nuit', 'prix_vente',
            'is_rentable', 'is_sellable', 'quantite_dispo',
        ]));

        return response()->json(['success' => true, 'data' => $mat->fresh(['fournisseur:id,first_name,last_name,email', 'category:id,nom'])]);
    }

    public function destroy($id)
    {
        Materielles::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Listing deleted.']);
    }

    public function addPhoto(Request $request, $id)
    {
        $mat = Materielles::with('photos')->findOrFail($id);

        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        $existingCount = $mat->photos->count();
        $path = $request->file('photo')->store('materielles', 'public');

        $photo = Photo::create([
            'materielle_id' => $mat->id,
            'path_to_img'   => $path,
            'is_cover'      => $existingCount === 0,
            'order'         => $existingCount,
        ]);

        return response()->json(['success' => true, 'data' => $photo]);
    }

    public function deletePhoto($id, $photoId)
    {
        $photo = Photo::where('id', $photoId)->where('materielle_id', $id)->firstOrFail();

        Storage::disk('public')->delete($photo->path_to_img);
        $wasCover = $photo->is_cover;
        $photo->delete();

        if ($wasCover) {
            $next = Photo::where('materielle_id', $id)->orderBy('order')->first();
            if ($next) $next->update(['is_cover' => true]);
        }

        return response()->json(['success' => true]);
    }

    public function bulkStatus(Request $request)
    {
        $request->validate([
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
            'status' => 'required|in:up,down',
        ]);

        Materielles::whereIn('id', $request->ids)->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => count($request->ids) . ' listing(s) updated.',
        ]);
    }
}
