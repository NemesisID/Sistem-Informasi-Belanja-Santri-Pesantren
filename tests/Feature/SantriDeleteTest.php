<?php

namespace Tests\Feature;

use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SantriDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_santri_deletes_associated_wali_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $santri = Santri::create([
            'nis' => '12345678',
            'nama' => 'Santri Test',
            'jenis_kelamin' => 'L',
            'unit' => 'MTS',
            'status' => 'aktif',
            'saldo' => 0,
        ]);

        $wali = $santri->syncWaliAccount();

        $this->assertNotNull($wali);
        $this->assertDatabaseHas('users', [
            'id' => $wali->id,
            'username' => '12345678',
            'deleted_at' => null,
        ]);

        $response = $this->actingAs($admin)->deleteJson("/api/santris/{$santri->id}");
        $response->assertOk();

        $this->assertSoftDeleted('santris', ['id' => $santri->id]);
        $this->assertSoftDeleted('users', ['id' => $wali->id]);
    }
}
