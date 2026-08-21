<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'lastname' => fake()->lastName(),
            'dni' => static::validCedula(),
            'phone' => $this->faker->numerify('09########'),
            'cellphone' => $this->faker->numerify('09########'),
            'address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'status' => 1,
        ];
    }

    /**
     * Genera una cédula ecuatoriana válida (módulo 10, provincia 17).
     */
    protected static function validCedula(): string
    {
        $digits = [1, 7, random_int(0, 5)];

        for ($i = 3; $i < 9; $i++) {
            $digits[$i] = random_int(0, 9);
        }

        $weights = [2, 1, 2, 1, 2, 1, 2, 1, 2];
        $sum = 0;

        foreach ($weights as $i => $weight) {
            $value = $digits[$i] * $weight;
            $sum += $value >= 10 ? $value - 9 : $value;
        }

        $digits[9] = (10 - ($sum % 10)) % 10;

        return implode('', $digits);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
