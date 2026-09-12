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

    public function test_penarikan_kedua_pada_hari_yang_sama_ditolak(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 100000]);

        // Sudah menarik 20000 hari ini
        $santri->transactions()->create([
            'tipe' => 'tarik_koin',
            'nominal' => -20000,
            'saldo_sebelum' => 120000,
            'saldo_setelah' => 100000,
            'created_by' => $this->staff->id,
            'created_at' => now(),
        ]);

        // Penarikan kedua di hari yang sama harus ditolak
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 15000,
        ])->assertStatus(422)->assertJsonValidationErrors('santri_id');

        $this->assertSame(100000, $santri->fresh()->saldo);
    }

    public function test_penarikan_di_hari_berbeda_diperbolehkan(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 100000]);

        // Penarikan kemarin
        $santri->transactions()->create([
            'tipe' => 'tarik_koin',
            'nominal' => -20000,
            'saldo_sebelum' => 120000,
            'saldo_setelah' => 100000,
            'created_by' => $this->staff->id,
            'created_at' => now()->subDay(),
        ]);

        // Penarikan hari ini diizinkan
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 15000,
        ])->assertCreated();

        $this->assertSame(85000, $santri->fresh()->saldo);
    }

    public function test_penarikan_nominal_di_atas_30ribu_diperbolehkan(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 100000]);

        // Nominal > 30.000 (misal 50.000) tanpa batas cap 30rb
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 50000,
        ])->assertCreated();

        $this->assertSame(50000, $santri->fresh()->saldo);
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

    public function test_penyesuaian_tetap_diperbolehkan_meski_sudah_menarik_koin_hari_ini(): void
    {
        $santri = Santri::factory()->withFoto()->create(['saldo' => 50000]);

        // Menarik koin hari ini
        $this->actingAs($this->staff)->postJson('/api/penarikan', [
            'santri_id' => $santri->id,
            'nominal' => 10000,
        ])->assertCreated();

        // Fitur penyesuaian (nabung/kredit) tetap diizinkan
        $this->actingAs($this->staff)->postJson("/api/santris/{$santri->id}/penyesuaian", [
            'aksi' => 'tambah',
            'nominal' => 20000,
            'keterangan' => 'Nabung koin',
        ])->assertOk();

        $this->assertSame(60000, $santri->fresh()->saldo);
    }
}
