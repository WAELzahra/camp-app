<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Cloudflare Turnstile (invisible CAPTCHA). Verified against Cloudflare's
            // siteverify endpoint below — if TURNSTILE_SECRET_KEY isn't configured
            // (e.g. local dev), the check is skipped rather than blocking signup.
            'cf_turnstile_token' => ['required', 'string', $this->turnstileRule()],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'phone_number' => ['required', 'string', 'max:20'],
            'ville' => ['nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date'],
            'sexe' => ['nullable', 'string', 'max:10'],
            'langue' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Rules\Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', 'string', 'exists:roles,name'],
            'invitation_token' => ['nullable', 'string', 'max:128'],

            // Make all these fields nullable instead of required
            'adresse' => ['nullable', 'string', 'max:500'],
            'capacite' => ['nullable', 'integer', 'min:1'],
            'services_offerts' => ['nullable', 'string'],
            'price_per_night' => ['nullable', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'nom_groupe' => ['nullable', 'string', 'max:255'],
            'cin_responsable' => ['nullable', 'string', 'max:50'],
            'experience' => ['nullable', 'integer', 'min:0'],
            'tarif' => ['nullable', 'numeric', 'min:0'],
            'zone_travail' => ['nullable', 'string', 'max:255'],
            'cin' => ['nullable', 'string', 'max:50'],
            'cin_fournisseur' => ['nullable', 'string', 'max:50'],
            'interval_prix' => ['nullable', 'string', 'max:100'],
            'product_category' => ['nullable', 'string', 'max:255'],
            'legal_document_file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'center_images.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,gif', 'max:5120'],

            // Must explicitly accept all active legal documents at registration.
            'cgu_accepted'      => ['required', 'boolean', 'accepted'],
        ];
    }

    /**
     * Verifies the Turnstile token against Cloudflare's siteverify endpoint.
     * Fails the field (blocking registration) only when a secret is configured
     * AND Cloudflare rejects the token — so local/staging envs without a
     * TURNSTILE_SECRET_KEY set keep working, but production is protected the
     * moment the key is added.
     */
    private function turnstileRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $secret = config('services.turnstile.secret');
            if (!$secret) {
                return;
            }

            try {
                $response = Http::asForm()->timeout(5)->post(
                    'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                    [
                        'secret'   => $secret,
                        'response' => $value,
                        'remoteip' => $this->ip(),
                    ]
                );

                if (!$response->successful() || !$response->json('success')) {
                    $fail("Vérification anti-robot échouée. Merci de réessayer.");
                }
            } catch (\Throwable $e) {
                Log::warning('Turnstile verification request failed', ['error' => $e->getMessage()]);
                $fail('Vérification anti-robot indisponible. Merci de réessayer.');
            }
        };
    }
}
