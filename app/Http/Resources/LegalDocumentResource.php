<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegalDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Serve the right language based on Accept-Language header; fall back to French.
        $lang = strtolower(substr($request->header('Accept-Language', 'fr'), 0, 2));
        $lang = in_array($lang, ['fr', 'en', 'ar']) ? $lang : 'fr';

        return [
            'id'             => $this->id,
            'type'           => $this->type,
            'type_label'     => $this->type_label,
            'version'        => $this->version,
            'effective_date' => $this->effective_date->toDateString(),
            'content'        => $this->contentForLocale($lang),
            'is_active'      => $this->is_active,
        ];
    }
}
