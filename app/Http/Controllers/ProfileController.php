<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
{
    $user = $request->user()->load([
        'profile.guide',
        'profile.centre',
        'profile.groupe',
        'profile.fournisseur'
    ]);

    return view('profile.edit', compact('user'));
}

public function update(ProfileUpdateRequest $request): RedirectResponse
{
    // 1. Mise à jour des données de l'utilisateur
    $user = $request->user();
    $user->fill($request->only(['name', 'email'])); // Ajoute les champs que tu veux autoriser

    if ($user->isDirty('email')) {
        $user->email_verified_at = null;
    }

    $user->save();

    // 2. Mise à jour des données du profil principal
    $profileData = $request->only([
        'date_naissance', 'sexe', 'bio', 'cover_image', 'feedback', 'adresse', 'immatricule'
    ]);

    $profile = $user->profile()->updateOrCreate([], $profileData);

    // 3. Mise à jour des données du profil enfant selon le rôle
    switch ($user->role) {
        case 'guide':
            $profile->guide()->updateOrCreate([], $request->only([
                'langue', 'experience', 'tarif', 'zone_travail'
            ]));
            break;
        case 'centre':
            $profile->centre()->updateOrCreate([], $request->only([
                'capacite', 'service_offrant', 'document_legal', 'disponibilite'
            ]));
            break;
        case 'groupe':
            $profile->groupe()->updateOrCreate([], $request->only([
                'nom_groupe', 'cin_responsable'
            ]));
            break;
        case 'fournisseur':
            $profile->fournisseur()->updateOrCreate([], $request->only([
                'intervale_prix', 'product_category'
            ]));
            break;
    }

    return Redirect::route('profile.edit')->with('status', 'profile-updated');
}


}
