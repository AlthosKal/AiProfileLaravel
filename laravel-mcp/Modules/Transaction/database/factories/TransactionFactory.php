<?php

namespace Modules\Transaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Transaction\Enums\TransactionType;
use Modules\Transaction\Models\Transaction;

class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'user_email' => $this->faker->safeEmail(),
            'amount' => $this->faker->randomFloat(2, 10),
            'description' => $this->faker->text(),
            'type' => $this->faker->randomElement([TransactionType::cases()])
        ];
    }
}
