<?php

namespace Tests\Feature;

use App\Models\Santri;
use App\Models\User;
use App\Services\SaldoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SaldoServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_kredit_menambah_saldo_dan_mencatat_ledger(): void
    {
        $santri = Santri::factory()->create(['saldo' => 10000]);

        $tx = app(SaldoService::class)->kredit($santri, 50000, 'topup', $this->staff, 'Top-up BNI');

        $this->assertSame(60000, $santri->fresh()->saldo);
        $this->assertSame('topup', $tx->tipe);
        $this->assertSame(10000, $tx->saldo_sebelum);
        $this->assertSame(60000, $tx->saldo_setelah);
        $this->assertSame(50000, $tx->nominal);
        $this->assertSame($this->staff->id, $tx->created_by);
    }

    public function test_debit_mengurangi_saldo_sesuai(): void
    {
        $santri = Santri::factory()->create(['saldo' => 50000]);

        $tx = app(SaldoService::class)->debit($santri, 15000, 'tarik_koin', $this->staff);

        $this->assertSame(35000, $santri->fresh()->saldo);
        $this->assertSame(-15000, $tx->nominal);
        $this->assertSame(35000, $tx->saldo_setelah);
    }

    public function test_debit_ditolak_saat_saldo_tidak_cukup(): void
    {
        $santri = Santri::factory()->create(['saldo' => 5000]);

        $this->expectException(ValidationException::class);

        app(SaldoService::class)->debit($santri, 15000, 'tarik_koin', $this->staff);

        $this->assertSame(5000, $santri->fresh()->saldo);
    }

    public function test_debit_selalu_mengunci_baris_dalam_transaksi(): void
    {
        // lockForUpdate dipanggil → saldo tidak pernah minus walau debit > saldo
        $santri = Santri::factory()->create(['saldo' => 0]);

        try {
            app(SaldoService::class)->debit($santri, 1000, 'tarik_koin', $this->staff);
            $this->fail('Seharusnya ditolak');
        } catch (ValidationException) {
            $this->assertSame(0, $santri->fresh()->saldo);
            $this->assertDatabaseCount('transactions', 0);
        }
    }
}
