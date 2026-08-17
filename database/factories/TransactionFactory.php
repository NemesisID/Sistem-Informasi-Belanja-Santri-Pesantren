<?php

namespace Database\Factories;

use App\Models\Santri;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nominal = fake()->numberBetween(1000, 50000);
        $saldoSebelum = fake()->numberBetween(0, 100000);

        return [
            'santri_id' => Santri::factory(),
            'tipe' => 'tarik_koin',
            'nominal' => -$nominal,
            'saldo_sebelum' => $saldoSebelum,
            'saldo_setelah' => $saldoSebelum - $nominal,
            'created_by' => User::factory()->staff(),
        ];
    }

    public function topup(int $nominal, int $saldoSebelum): static
    {
        return $this->state(fn () => [
            'tipe' => 'topup',
            'nominal' => $nominal,
            'saldo_sebelum' => $saldoSebelum,
            'saldo_setelah' => $saldoSebelum + $nominal,
        ]);
    }

    public function tarikKoin(int $nominal, int $saldoSebelum): static
    {
        return $this->state(fn () => [
            'tipe' => 'tarik_koin',
            'nominal' => -$nominal,
            'saldo_sebelum' => $saldoSebelum,
            'saldo_setelah' => $saldoSebelum - $nominal,
        ]);
    }
}
