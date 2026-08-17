<?php

namespace Tests\Feature;

use App\Models\Santri;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenarikanTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->staff = User::factory()->staff()->create();
    }

    public function test_penarikan_berhasil_mengurangi_saldo_dan_mencatat_created_by(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 50000]);

        $response = $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 20000,
        ]);

        $response->assertCreated()->assertJsonPath('transaction.tipe', 'tarik_koin');
        $this->assertSame(30000, $santri->fresh()->saldo);
        $this->assertSame($this->staff->id, $santri->transactions()->latest('id')->first()->created_by);
    }

    public function test_penarikan_berhasil_meski_santri_tidak_punya_foto(): void
    {
        $santri = Santri::factory()->create(['saldo' => 50000]); // foto_path null

        $response = $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 10000,
        ]);

        $response->assertCreated()->assertJsonPath('transaction.tipe', 'tarik_koin');
        $this->assertSame(40000, $santri->fresh()->saldo);
    }

    public function test_penarikan_ditolak_saat_saldo_tidak_cukup(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 5000]);

        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 10000,
        ])->assertStatus(422);

        $this->assertSame(5000, $santri->fresh()->saldo);
    }

    public function test_penarikan_ditolak_saat_melebihi_batas_2_hari_rolling(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 100000]);

        // Sudah menarik 20000 dalam 2 hari terakhir
        $santri->transactions()->create([
            'tipe' => 'tarik_koin',
            'nominal' => -20000,
            'saldo_sebelum' => 120000,
            'saldo_setelah' => 100000,
            'created_by' => $this->staff->id,
        ]);

        // 20000 + 15000 = 35000 > 30000 → ditolak
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 15000,
        ])->assertStatus(422)->assertJsonValidationErrors('nominal');

        $this->assertSame(100000, $santri->fresh()->saldo);
    }

    public function test_penarikan_tepat_di_batas_2_hari_diperbolehkan(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 50000]);

        // 30000 tepat di batas → boleh
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 30000,
        ])->assertCreated();

        $this->assertSame(20000, $santri->fresh()->saldo);
    }

    public function test_wali_tidak_bisa_melakukan_penarikan(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 50000]);
        $wali = User::factory()->wali()->create();

        $this->actingAs($wali)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 10000,
        ])->assertForbidden();
    }

    public function test_tarik_lama_di_luar_2_hari_tidak_dihitung_batas(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 100000]);

        Transaction::create([
            'santri_id' => $santri->id,
            'tipe' => 'tarik_koin',
            'nominal' => -25000,
            'saldo_sebelum' => 125000,
            'saldo_setelah' => 100000,
            'created_by' => $this->staff->id,
            'created_at' => now()->subDays(3),
        ]);

        // 3 hari lalu tidak dihitung → 30000 dalam 2 hari terakhir masih boleh
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 30000,
        ])->assertCreated();
    }
}
