<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Wraps ProfileGroupe.
 * patente_path, id_album_photo, id_annonce are never exposed.
 * has_patente bool is exposed to self only.
 */
class ProfileGroupeResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'nom_groupe' => $this->nom_groupe,
        ];

        if ($this->isSelf) {
            $data['has_patente'] = (bool) $this->patente_path;
        }

        return $data;
    }
}
