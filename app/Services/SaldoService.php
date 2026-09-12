<?php

namespace App\Services;

use App\Models\Santri;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Satu-satunya jalur mutasi saldo (kredit/debit).
 * Selalu memakai lock baris + transaksi DB agar saldo tidak pernah negatif
 * dan tidak terjadi race condition antar loket.
 */
class SaldoService
{
    /**
     * Kredit saldo (+masuk). Digunakan untuk top-up BNI dan penyesuaian tambah.
     */
    public function kredit(
        Santri $santri,
        int $nominal,
        string $tipe,
        User $by,
        ?string $keterangan = null,
        ?int $bniUploadItemId = null,
    ): Transaction {
        return DB::transaction(function () use ($santri, $nominal, $tipe, $by, $keterangan, $bniUploadItemId) {
            $locked = Santri::whereKey($santri->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->saldo;
            $after = $before + $nominal;

            $locked->update(['saldo' => $after]);

            return $this->catat($locked, $tipe, $nominal, $before, $after, $by, $keterangan, $bniUploadItemId);
        });
    }

    /**
     * Debit saldo (−keluar). Tolak bila saldo tidak cukup.
     */
    public function debit(
        Santri $santri,
        int $nominal,
        string $tipe,
        User $by,
        ?string $keterangan = null,
    ): Transaction {
        return DB::transaction(function () use ($santri, $nominal, $tipe, $by, $keterangan) {
            $locked = Santri::whereKey($santri->id)->lockForUpdate()->firstOrFail();
            $before = (int) $locked->saldo;

            if ($before < $nominal) {
                throw ValidationException::withMessages([
                    'saldo' => "Saldo santri tidak cukup (saldo Rp {$before}).",
                ]);
            }

            $after = $before - $nominal;
            $locked->update(['saldo' => $after]);

            return $this->catat($locked, $tipe, -$nominal, $before, $after, $by, $keterangan);
        });
    }

    /**
     * Penarikan koin: cek batas per transaksi, lalu debit.
     */
    public function withdrawal(Santri $santri, int $nominal, User $by): Transaction
    {
        return DB::transaction(function () use ($santri, $nominal, $by) {
            $locked = Santri::whereKey($santri->id)->lockForUpdate()->firstOrFail();

            $sudahPenarikanHariIni = $locked->transactions()
                ->where('tipe', 'tarik_koin')
                ->whereDate('created_at', now()->toDateString())
                ->exists();

            if ($sudahPenarikanHariIni) {
                throw ValidationException::withMessages([
                    'santri_id' => 'Pengambilan koin hanya dapat dilakukan 1 kali sehari.',
                ]);
            }

            $before = (int) $locked->saldo;
            if ($before < $nominal) {
                throw ValidationException::withMessages([
                    'saldo' => "Saldo santri tidak cukup (saldo Rp {$before}).",
                ]);
            }

            $after = $before - $nominal;
            $locked->update(['saldo' => $after]);

            return $this->catat($locked, 'tarik_koin', -$nominal, $before, $after, $by);
        });
    }

    private function catat(
        Santri $santri,
        string $tipe,
        int $nominal,
        int $before,
        int $after,
        User $by,
        ?string $keterangan = null,
        ?int $bniUploadItemId = null,
    ): Transaction {
        return $santri->transactions()->create([
            'bni_upload_item_id' => $bniUploadItemId,
            'tipe' => $tipe,
            'nominal' => $nominal,
            'saldo_sebelum' => $before,
            'saldo_setelah' => $after,
            'keterangan' => $keterangan,
            'created_by' => $by->id,
        ]);
    }
}
