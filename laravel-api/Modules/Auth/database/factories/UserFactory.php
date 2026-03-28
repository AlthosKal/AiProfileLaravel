<?php

namespace Modules\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Enums\SecurityStatusEnum;
use Modules\Auth\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'security_status' => SecurityStatusEnum::UNBLOCKED,
        ];
    }

    public function permanentlyBlocked(): static
    {
        return $this->state(['security_status' => SecurityStatusEnum::PERMANENTLY_BLOCKED]);
    }
}
