<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Root resource class for all API responses.
 *
 * Strips every field whose key is a raw numeric database ID (ends in `_id`,
 * or is the literal `id`) before the response reaches the client.
 * Subclasses should override toArray() and call parent::toArray() last,
 * or call stripIds() on their own array.
 *
 * Fields that are ALWAYS removed:
 *   id, user_id, profile_id, and any key matching /*_id$/
 *
 * Fields deliberately kept:
 *   uuid  — stable public identifier exposed to clients
 */
abstract class BaseApiResource extends JsonResource
{
    // Disable the automatic {"data": {...}} wrapper so responses are
    // flat objects and the frontend reads response.data directly.
    public static $wrap = null;
    protected array $internalIdFields = [
        'id',
        'user_id',
        'profile_id',
        'role_id',
        'provider_id',
        'payment_id',
        'event_id',
        'group_id',
        'centre_id',
        'fournisseur_id',
        'created_by',
        'updated_by',
        'album_id',
        'boutique_id',
        'materielle_id',
        'reservation_id',
    ];

    /**
     * Controls self vs. public view for profile sub-resources.
     * Default is false (public view). Call withSelf(true) from the controller
     * when the authenticated user is the profile owner.
     */
    protected bool $isSelf = false;

    public function withSelf(bool $isSelf = true): static
    {
        $this->isSelf = $isSelf;
        return $this;
    }

    /**
     * Remove internal ID fields from an array of resource data.
     * Also strips any key ending in _id that is not explicitly needed.
     */
    protected function stripIds(array $data): array
    {
        foreach ($data as $key => $value) {
            // Remove fields explicitly listed, plus any unknown *_id column
            if (in_array($key, $this->internalIdFields, true) || str_ends_with($key, '_id')) {
                unset($data[$key]);
            }
        }
        return $data;
    }
}
