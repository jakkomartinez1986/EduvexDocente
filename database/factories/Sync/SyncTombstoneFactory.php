<?php

namespace Database\Factories\Sync;

use App\Models\Sync\SyncTombstone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SyncTombstone>
 */
class SyncTombstoneFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entity' => $this->faker->randomElement(['attendance', 'activity_grade']),
            'entity_id' => $this->faker->unique()->numberBetween(1, 1000000),
            'owner_user_id' => null,
            'deleted_at' => now(),
        ];
    }
}
