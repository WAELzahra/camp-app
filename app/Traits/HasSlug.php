<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = static::buildUniqueSlug($model, null);
            }
        });

        static::updating(function ($model) {
            $source = $model->getSlugSource();
            if ($model->isDirty($source) || empty($model->slug)) {
                $model->slug = static::buildUniqueSlug($model, $model->id);
            }
        });
    }

    public static function buildUniqueSlug(self $model, ?int $excludeId): string
    {
        $source = $model->getSlugSource();
        $base   = Str::slug($model->$source) ?: 'item';
        $slug   = $base;
        $i      = 2;

        while (
            static::where('slug', $slug)
                ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
                ->exists()
        ) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function getSlugSource(): string
    {
        return $this->slugSource ?? 'nom';
    }
}
