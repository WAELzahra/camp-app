<?php

namespace App\Services\AI;

use App\Models\ProfileCampeur;
use Illuminate\Support\Facades\Log;

class ProfileExtractorService
{
    public function getMissingCriticalFields(ProfileCampeur $profile): array
    {
        $missing = [];

        if (empty($profile->skill_level)) {
            $missing[] = 'skill_level';
        }
        if (empty($profile->budget_range)) {
            $missing[] = 'budget_range';
        }
        if (empty($profile->comfort_level)) {
            $missing[] = 'comfort_level';
        }
        if (empty($this->decodeJsonField($profile->preferred_trip_styles))) {
            $missing[] = 'preferred_trip_styles';
        }
        if (empty($this->decodeJsonField($profile->preferred_activities))) {
            $missing[] = 'preferred_activities';
        }

        return $missing;
    }

    public function isSufficientToProcess(ProfileCampeur $profile): bool
    {
        return ! empty($profile->skill_level) && ! empty($profile->budget_range);
    }

    public function extractProfileDataFromMessage(string $message): array
    {
        $lower    = mb_strtolower($message);
        $extracted = [];

        // ── Skill level (highest specificity first) ───────────────────────────
        $skillMap = [
            'expert'       => ['expert', 'professionnel', 'guide', 'très expérimenté'],
            'advanced'     => ['avancé', 'expérimenté', "beaucoup d'expérience", 'advanced', 'experienced'],
            'intermediate' => ['intermédiaire', 'quelques fois', "un peu d'expérience", 'intermediate', 'some experience'],
            'beginner'     => ['débutant', 'novice', 'première fois', 'jamais campé', 'beginner', 'first time', 'new to camping'],
        ];

        foreach ($skillMap as $level => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $extracted['skill_level'] = $level;
                    break 2;
                }
            }
        }

        // ── Budget (premium before moderate before budget to avoid substring clash) ──
        $budgetMap = [
            'premium'  => ['premium', 'luxe', 'confortable', 'haut de gamme', 'luxury', 'high end', 'glamping'],
            'moderate' => ['modéré', 'raisonnable', 'moyen', 'moderate', 'mid-range'],
            'budget'   => ['pas cher', 'économique', 'budget', 'moins cher', 'cheap', 'low budget', 'économiser'],
        ];

        foreach ($budgetMap as $range => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $extracted['budget_range'] = $range;
                    break 2;
                }
            }
        }

        // ── Comfort level ─────────────────────────────────────────────────────
        $comfortMap = [
            'glamping' => ['glamping', 'hébergement'],
            'basic'    => ['basique', 'rustique', 'tente simple', 'basic', 'rough'],
            'standard' => ['standard', 'normal', 'classique'],
        ];

        foreach ($comfortMap as $level => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $extracted['comfort_level'] = $level;
                    break 2;
                }
            }
        }

        // ── Trip styles ───────────────────────────────────────────────────────
        $styleKeywords = [
            'montagne', 'forêt', 'désert', 'côtier', 'plage', 'randonnée',
            'famille', 'solo', 'groupe', 'aventure',
            'mountain', 'forest', 'desert', 'coastal', 'beach', 'hiking', 'family', 'adventure',
        ];

        $detectedStyles = [];
        foreach ($styleKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                $detectedStyles[] = $kw;
            }
        }
        if (! empty($detectedStyles)) {
            $extracted['preferred_trip_styles'] = $detectedStyles;
        }

        return $extracted;
    }

    public function saveExtractedProfile(ProfileCampeur $profile, array $extracted): ProfileCampeur
    {
        $arrayFields = ['preferred_trip_styles', 'preferred_activities'];
        $updates     = [];

        foreach ($extracted as $field => $value) {
            if (in_array($field, $arrayFields, true)) {
                $existing        = $this->decodeJsonField($profile->$field);
                $merged          = array_values(array_unique(array_merge($existing, (array) $value)));
                $updates[$field] = json_encode($merged);
            } else {
                $updates[$field] = $value;
            }
        }

        if (! empty($updates)) {
            $profile->update($updates);
        }

        Log::info('profile_updated_from_chat', [
            'profile_id'     => $profile->profile_id ?? $profile->id,
            'fields_updated' => array_keys($extracted),
        ]);

        return $profile->fresh() ?? $profile;
    }

    public function buildProfileCompletionQuestion(array $missingFields): string
    {
        if (empty($missingFields)) {
            return '';
        }

        $priority = ['skill_level', 'budget_range', 'comfort_level', 'preferred_trip_styles', 'preferred_activities'];

        $top = null;
        foreach ($priority as $field) {
            if (in_array($field, $missingFields, true)) {
                $top = $field;
                break;
            }
        }

        return match ($top) {
            'skill_level'           => "Pour vous proposer les meilleures zones, quel est votre niveau en camping ? (débutant / intermédiaire / avancé / expert)",
            'budget_range'          => "Quel est votre budget pour ce voyage ? (économique / modéré / premium)",
            'comfort_level'         => "Quel niveau de confort préférez-vous ? (basique / standard / glamping)",
            'preferred_trip_styles' => "Quel type de terrain préférez-vous ? (montagne, forêt, désert, côtier, plage...)",
            'preferred_activities'  => "Quelles activités vous intéressent ? (randonnée, escalade, baignade, photographie nature...)",
            default                 => '',
        };
    }

    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '' && $value !== '[]') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
