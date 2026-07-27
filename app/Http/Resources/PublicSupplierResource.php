<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * The `fournisseur` user shown on public boutique/shop pages.
 *
 * Unlike UserResource, email and phone_number are intentionally public here —
 * shop pages display them as the supplier's contact card. Everything else
 * that isn't needed for that (birthdate, gender, language, address, login
 * activity, raw email_verified_at timestamp, ...) is left out.
 *
 * Signed-out visitors only ever get a masked email/phone — the frontend
 * shows these masked and gates the actual tel:/mailto: actions behind a
 * sign-in prompt, so the API must not hand out the real values either, or
 * that gate is just cosmetic. Signed-in users get the real values.
 */
class PublicSupplierResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        $isGuest = ! $request->user();

        return [
            'uuid'         => $this->uuid,
            'first_name'   => $this->first_name,
            'last_name'    => $this->last_name,
            'avatar'       => $this->avatar ? storage_url($this->avatar) : null,
            'email'        => $isGuest ? self::maskEmail($this->email) : $this->email,
            'phone_number' => $isGuest ? self::maskPhone($this->phone_number) : $this->phone_number,
            'ville'        => $this->ville,
            'is_verified'  => $this->email_verified_at !== null,
            'profile'      => $this->whenLoaded('profile', fn () => [
                'city'    => $this->profile?->city,
                'address' => $this->profile?->address,
            ]),
        ];
    }

    // Mirrors the frontend's maskEmail() exactly (SupplierProfile.tsx et al.)
    // so the masked value the API sends matches what guests already see.
    private static function maskEmail(?string $email): ?string
    {
        if (!$email || !str_contains($email, '@')) return $email;
        [$local, $domain] = explode('@', $email, 2);
        if (mb_strlen($local) <= 2) return $email;
        $masked = $local[0] . str_repeat('•', min(mb_strlen($local) - 2, 6)) . mb_substr($local, -1);
        return "{$masked}@{$domain}";
    }

    // Mirrors the frontend's maskPhoneNumber() exactly.
    private static function maskPhone(?string $phone): ?string
    {
        if (!$phone) return $phone;
        $last4 = mb_substr($phone, -4);
        $maskedLength = mb_strlen($phone) - 4;
        return str_repeat('•', min(max($maskedLength, 0), 8)) . $last4;
    }
}
