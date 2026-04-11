<?php

namespace Modules\Transaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Transaction\Enums\FileType;
use Modules\Transaction\Models\File;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = File::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'path' => $this->faker->filePath(),
            'type' => $this->faker->randomElement(FileType::cases()),
        ];
    }
}
