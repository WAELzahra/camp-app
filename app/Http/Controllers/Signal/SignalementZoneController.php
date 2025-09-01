<?php

namespace App\Http\Controllers\Signal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Signales;

class SignalementZoneController extends Controller
{

    // Création d’un signalement
    
    public function store(Request $request, $zoneId)
    {
        $data = $request->validate([
            'contenu' => 'required|string|max:255',
            'photo' => 'nullable|image|max:2048', // photo optionnelle
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('signales', 'public');
        }

        $signalement = Signales::create([
            'user_id' => auth()->id(),
            'zone_id' => $zoneId,
            'target_id' => null,
            'type' => 'zone',
            'contenu' => $data['contenu'],
            'photo' => $data['photo'] ?? null,
            'status' => 'pending', // nouveau champ pour validation admin
        ]);

        return response()->json([
            'message' => 'Signalement enregistré',
            'data' => $signalement
        ], 201);
    }
}

