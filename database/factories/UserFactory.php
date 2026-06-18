<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 *
 * Role ids follow the order seeded by RoleSeeder:
 *   1 campeur · 2 organizer · 3 centre · 4 fournisseur · 5 guide · 6 admin
 */
class UserFactory extends Factory
{
    /** Role id constants — keep in sync with database/seeders/RoleSeeder.php */
    public const ROLE_CAMPEUR = 1;

    public const ROLE_ORGANIZER = 2;

    public const ROLE_CENTRE = 3;

    public const ROLE_FOURNISSEUR = 4;

    public const ROLE_GUIDE = 5;

    public const ROLE_ADMIN = 6;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone_number' => fake()->numerify('########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => self::ROLE_CAMPEUR,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(int $roleId): static
    {
        return $this->state(fn (array $attributes) => ['role_id' => $roleId]);
    }

    public function camper(): static
    {
        return $this->role(self::ROLE_CAMPEUR);
    }

    public function organizer(): static
    {
        return $this->role(self::ROLE_ORGANIZER);
    }

    public function centre(): static
    {
        return $this->role(self::ROLE_CENTRE);
    }

    public function fournisseur(): static
    {
        return $this->role(self::ROLE_FOURNISSEUR);
    }

    public function guide(): static
    {
        return $this->role(self::ROLE_GUIDE);
    }

    public function admin(): static
    {
        return $this->role(self::ROLE_ADMIN);
    }
}
