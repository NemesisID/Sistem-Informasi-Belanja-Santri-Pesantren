<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Santri>
 */
class SantriFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nis' => fake()->unique()->numerify('##########'),
            'nama' => fake()->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'unit' => fake()->randomElement(['MTS', 'MA', 'SMP', 'SMA', 'SMK', 'BARU']),
            'status' => 'aktif',
            'saldo' => 0,
        ];
    }

    public function withFoto(): static
    {
        return $this->state(fn () => ['foto_path' => 'santri-foto/'.fake()->numerify('#######').'.jpg']);
    }

    public function aktif(): static
    {
        return $this->state(fn () => ['status' => 'aktif']);
    }
}
