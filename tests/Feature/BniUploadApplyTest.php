<?php

namespace Tests\Feature;

use App\Models\BniUpload;
use App\Models\BniUploadItem;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BniUploadApplyTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
    }

    public function test_apply_mengkredit_saldo_item_valid(): void
    {
        $santri = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '9880012345']);
        $upload = BniUpload::factory()->create(['status' => 'menunggu', 'uploaded_by' => $this->staff->id]);
        BniUploadItem::factory()->valid()->create([
            'upload_id' => $upload->id,
            'va' => '9880012345',
            'nominal' => 75000,
            'santri_id' => $santri->id,
        ]);

        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$upload->id}/apply")
            ->assertOk();

        $this->assertSame(75000, $santri->fresh()->saldo);
        $this->assertDatabaseHas('bni_upload_items', ['upload_id' => $upload->id, 'diterapkan' => true]);
        $this->assertSame('terapkan', $upload->fresh()->status);
        // Transaksi topup tercatat dengan created_by staff
        $this->assertDatabaseHas('transactions', [
            'santri_id' => $santri->id,
            'tipe' => 'topup',
            'nominal' => 75000,
            'created_by' => $this->staff->id,
        ]);
    }

    public function test_item_invalid_tidak_mengkredit_saldo(): void
    {
        $santri = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '9880012345']);
        $upload = BniUpload::factory()->create(['status' => 'menunggu', 'uploaded_by' => $this->staff->id]);

        // Valid + invalid (VA tidak cocok)
        BniUploadItem::factory()->valid()->create([
            'upload_id' => $upload->id, 'va' => '9880012345', 'nominal' => 30000, 'santri_id' => $santri->id,
        ]);
        BniUploadItem::factory()->invalid()->create([
            'upload_id' => $upload->id, 'va' => '9880099999', 'nominal' => 99000, 'santri_id' => null,
        ]);

        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$upload->id}/apply")->assertOk();

        // Hanya item valid yang menambah saldo
        $this->assertSame(30000, $santri->fresh()->saldo);
        $this->assertDatabaseHas('bni_upload_items', ['upload_id' => $upload->id, 'va' => '9880099999', 'diterapkan' => false]);
    }

    public function test_apply_dua_kali_ditolak(): void
    {
        $santri = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '9880012345']);
        $upload = BniUpload::factory()->create(['status' => 'menunggu', 'uploaded_by' => $this->staff->id]);
        BniUploadItem::factory()->valid()->create([
            'upload_id' => $upload->id, 'va' => '9880012345', 'nominal' => 50000, 'santri_id' => $santri->id,
        ]);

        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$upload->id}/apply")->assertOk();
        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$upload->id}/apply")->assertStatus(422);

        // Tidak double credit
        $this->assertSame(50000, $santri->fresh()->saldo);
    }

    public function test_apply_gagal_ditengah_mengakibatkan_rollback_total(): void
    {
        $santriA = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '9880011111']);
        $santriB = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '9880022222']);
        $upload = BniUpload::factory()->create(['status' => 'menunggu', 'uploaded_by' => $this->staff->id]);

        BniUploadItem::factory()->valid()->create([
            'upload_id' => $upload->id, 'va' => '9880011111', 'nominal' => 20000, 'santri_id' => $santriA->id,
        ]);
        BniUploadItem::factory()->valid()->create([
            'upload_id' => $upload->id, 'va' => '9880022222', 'nominal' => 40000, 'santri_id' => $santriB->id,
        ]);

        // Santri pertama di-soft-delete → kredit gagal (firstOrFail throw) → rollback
        $santriA->delete();

        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$upload->id}/apply")->assertStatus(500);

        // Tidak ada yang terlanjur ter-credit
        $this->assertSame(0, $santriB->fresh()->saldo);
        $this->assertDatabaseHas('bni_upload_items', ['upload_id' => $upload->id, 'va' => '9880022222', 'diterapkan' => false]);
        $this->assertSame('menunggu', $upload->fresh()->status);
        $this->assertDatabaseCount('transactions', 0);
    }
}
