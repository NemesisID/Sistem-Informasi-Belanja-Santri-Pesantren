<?php

namespace Database\Factories;

use App\Models\BniUpload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BniUploadItem>
 */
class BniUploadItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'upload_id' => BniUpload::factory(),
            'va' => fake()->numerify('###############'),
            'nama' => fake()->name(),
            'nominal' => fake()->numberBetween(10000, 200000),
            'tanggal' => fake()->date(),
            'journal' => fake()->unique()->numerify('######'),
            'billing_id' => fake()->regexify('JJN[A-Z]{3}[0-9]{2}/[0-9]{1,4}'),
            'dedup_key' => 'J:'.fake()->unique()->numerify('######'),
            'status_valid' => true,
            'diterapkan' => false,
        ];
    }

    public function invalid(string $catatan = 'VA tidak ditemukan'): static
    {
        return $this->state(fn () => [
            'status_valid' => false,
            'catatan' => $catatan,
        ]);
    }

    public function valid(): static
    {
        return $this->state(fn () => ['status_valid' => true, 'catatan' => null]);
    }
}
