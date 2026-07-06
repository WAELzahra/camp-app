<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Wraps ProfileCentre.
 * legal_document path is never exposed.
 * has_legal_document is public; legal_document_url is self-only.
 */
class ProfileCentreResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id'                         => $this->id,
            'name'                       => $this->name,
            'capacite'                   => $this->capacite,
            'price_per_night'            => $this->price_per_night,
            'category'                   => $this->category,
            'host_type'                  => $this->host_type,
            'effective_host_type'        => $this->resource->exists ? $this->effective_host_type : null,
            'disponibilite'              => (bool) $this->disponibilite,
            'latitude'                   => $this->latitude,
            'longitude'                  => $this->longitude,
            'contact_email'              => $this->contact_email,
            'contact_phone'              => $this->contact_phone,
            'manager_name'               => $this->manager_name,
            'established_date'           => $this->established_date,
            'has_legal_document'         => (bool) $this->legal_document,
            'document_legal_type'        => $this->document_legal_type,
            'document_legal_expiration'  => $this->document_legal_expiration,
        ];

        if ($this->isSelf && $this->legal_document) {
            // Legal documents are encrypted at rest — served decrypted through
            // the authenticated endpoint, never as a raw storage URL.
            $data['legal_document_url'] = url('/api/kyc/legal-document');
        }

        return $data;
    }
}
