<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BniUpload>
 */
class BniUploadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama_file' => 'mutasi_'.fake()->numerify('######').'.xlsx',
            'path' => 'bni-uploads/'.fake()->uuid().'.xlsx',
            'status' => 'menunggu',
            'jumlah_total' => 0,
            'jumlah_valid' => 0,
            'jumlah_invalid' => 0,
            'uploaded_by' => User::factory()->staff(),
        ];
    }
}
