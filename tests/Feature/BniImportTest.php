<?php

namespace Tests\Feature;

use App\Models\BniUpload;
use App\Models\BniUploadItem;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Format mutasi BNI e-Collection (paydata CSV) + aturan jajan:
 * - Hanya Billing ID diawali JJN yang diambil (PEM* diabaikan).
 * - VA dicocokkan ke santri lewat nomor customer (VA minus Prefix minus Client).
 * - Re-upload file yang sama tidak meng-kredit double (dedup via journal).
 */
class BniImportTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->staff = User::factory()->staff()->create();
    }

    /** Sampel paydata CSV sesuai format asli BNI (dengan wrapper ="..."). */
    private function csvJajan(): string
    {
        return implode("\n", [
            'Account Number;Prefix;Client Name;VA Number;Customer Name;Payment Date;Journal Number;Payment Amount;Billing ID;Currency',
            '="8384443334";="988";="44333";="9884433352026355";="Ibu Aisyah";="2026-07-29 21:13:33";="916382";="100000";="JJNJULI26/1136";="IDR"',
            '="8384443334";="988";="44333";="9884433302422025";="KLINIK NATA MEDIKA";="2026-07-29 20:03:47";="941964";="90000";="PEMBAYARAN";="IDR"',
            '="8384443334";="988";="44333";="9884433352026369";="Bpk Wafir";="2026-07-29 14:37:51";="975278";="200000";="JJNJULI26/1150";="IDR"',
            '="8384443334";="988";="44333";="9884433300001111";="Bpk Anonim";="2026-07-29 10:00:00";="100001";="50000";="JJNJULI26/1";="IDR"',
        ]);
    }

    private function uploadCsv(): array
    {
        $file = UploadedFile::fake()->createWithContent('paydata.csv', $this->csvJajan());

        return $this->actingAs($this->staff)
            ->post('/api/bni-uploads', ['file' => $file], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('upload');
    }

    public function test_upload_hanya_ambil_billing_jjn_dan_cocok_va_ke_santri(): void
    {
        // Nomor customer dari file: VA 9884433352026355 → 52026355, 9884433352026369 → 52026369
        $santriA = Santri::factory()->create(['va_jajan' => '52026355']);
        $santriB = Santri::factory()->create(['va_jajan' => '52026369']);

        $upload = $this->uploadCsv();

        // 4 baris data, 1 PEM di-skip → 3 item
        $this->assertSame(3, $upload['jumlah_total']);
        $this->assertSame(2, $upload['jumlah_valid']);
        $this->assertSame(1, $upload['jumlah_invalid']);

        $items = collect($upload['items']);

        // Baris JJN cocok ke santri via nomor customer VA (52026355) → valid, nama dari file
        $a = $items->where('va', '9884433352026355')->first();
        $this->assertSame($santriA->id, $a['santri']['id']);
        $this->assertSame($santriA->nis, $a['santri']['nis']);
        $this->assertSame('Ibu Aisyah', $a['nama']);
        $this->assertSame('JJNJULI26/1136', $a['billing_id']);
        $this->assertSame('916382', $a['journal']);

        $b = $items->where('va', '9884433352026369')->first();
        $this->assertSame($santriB->id, $b['santri']['id']);

        // Baris JJN yang VA-nya tidak cocok santri mana pun → invalid, tanpa santri
        $c = $items->where('va', '9884433300001111')->first();
        $this->assertFalse($c['status_valid']);
        $this->assertNull($c['santri']);
        $this->assertStringContainsString('tidak cocok', $c['catatan']);

        // Baris PEMBAYARAN (PEM*) tidak ikut ter-import sama sekali
        $this->assertCount(3, $items);
        $this->assertFalse($items->contains('billing_id', 'PEMBAYARAN'));
    }

    public function test_upload_ulang_file_sama_tidak_double_credit(): void
    {
        $santriA = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '52026355']);
        $santriB = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '52026369']);

        // Upload 1 → semua item valid (2), apply → saldo terisi
        $u1 = $this->uploadCsv();
        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$u1['id']}/apply")->assertOk();
        $this->assertSame(100000, $santriA->fresh()->saldo);
        $this->assertSame(200000, $santriB->fresh()->saldo);

        // Upload 2 (file sama, mis. download ulang) → semua item terdeteksi duplikat
        $u2 = $this->uploadCsv();
        $this->assertSame(0, $u2['jumlah_valid']);
        $this->assertSame(3, $u2['jumlah_invalid']);
        $this->assertStringContainsString('Duplikat', $u2['items'][0]['catatan']);

        // Apply upload 2 → tidak ada kredit tambahan
        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$u2['id']}/apply")
            ->assertOk()
            ->assertJson(['message' => 'Saldo berhasil dikredit dari 0 item valid.']);

        $this->assertSame(100000, $santriA->fresh()->saldo);
        $this->assertSame(200000, $santriB->fresh()->saldo);
        $this->assertSame(300000, Santri::sum('saldo'));
    }

    public function test_apply_dua_upload_data_journal_sama_tidak_double_credit(): void
    {
        $santri = Santri::factory()->create(['saldo' => 0, 'va_jajan' => '52026355']);

        $u1 = BniUpload::factory()->create(['status' => 'menunggu', 'uploaded_by' => $this->staff->id]);
        $u2 = BniUpload::factory()->create(['status' => 'menunggu', 'uploaded_by' => $this->staff->id]);

        // Transaksi yang sama (journal 916382) masuk di dua upload berbeda
        BniUploadItem::factory()->valid()->create([
            'upload_id' => $u1->id, 'va' => '52026355', 'nominal' => 100000,
            'santri_id' => $santri->id, 'journal' => '916382', 'dedup_key' => 'J:916382',
        ]);
        BniUploadItem::factory()->valid()->create([
            'upload_id' => $u2->id, 'va' => '52026355', 'nominal' => 100000,
            'santri_id' => $santri->id, 'journal' => '916382', 'dedup_key' => 'J:916382',
        ]);

        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$u1->id}/apply")->assertOk();
        $this->actingAs($this->staff)->postJson("/api/bni-uploads/{$u2->id}/apply")->assertOk();

        // Tidak double credit
        $this->assertSame(100000, $santri->fresh()->saldo);
        $this->assertSame(1, $santri->transactions()->count());
        // Item duplikat di upload 2 ditandai invalid + diterapkan (bukan dikredit)
        $this->assertDatabaseHas('bni_upload_items', [
            'upload_id' => $u2->id,
            'status_valid' => false,
            'diterapkan' => true,
            'catatan' => 'Duplikat: transaksi sama sudah dikredit di upload lain.',
        ]);
    }
}
