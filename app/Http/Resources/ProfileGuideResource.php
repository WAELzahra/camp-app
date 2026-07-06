<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Wraps ProfileGuide.
 * certificat_path is never exposed as a path.
 * has_certificat is visible to everyone; certificat_url is self-only.
 */
class ProfileGuideResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'experience'              => $this->experience,
            'tarif'                   => $this->tarif,
            'zone_travail'            => $this->zone_travail,
            'has_certificat'          => (bool) $this->certificat_path,
            'certificat_type'         => $this->certificat_type,
            'certificat_expiration'   => $this->certificat_expiration,
        ];

        if ($this->isSelf && $this->certificat_path) {
            // Encrypted at rest — served decrypted through the authenticated endpoint
            $data['certificat_url'] = url('/api/my/documents/certificat');
        }

        return $data;
    }
}
