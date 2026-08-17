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
     * Penarikan koin: cek batas Rp 30.000 / rolling 2 hari, lalu debit.
     */
    public function withdrawal(Santri $santri, int $nominal, User $by): Transaction
    {
        return DB::transaction(function () use ($santri, $nominal, $by) {
            $locked = Santri::whereKey($santri->id)->lockForUpdate()->firstOrFail();

            if (! $this->dalamBatasTarik($locked, $nominal)) {
                throw ValidationException::withMessages([
                    'nominal' => 'Melebihi batas penarikan Rp '.number_format(config('koin.batas_tarik_2hari'))
                        .' per 2 hari.',
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

    /**
     * Total penarikan santri pada rolling window 2 hari terakhir.
     */
    public function totalTarikDuaHari(Santri $santri): int
    {
        return (int) Transaction::where('santri_id', $santri->id)
            ->where('tipe', 'tarik_koin')
            ->where('created_at', '>=', now()->subDays(2))
            ->sum('nominal');
    }

    /**
     * Apakah nominal masih dalam batas tarik 2 hari?
     */
    public function dalamBatasTarik(Santri $santri, int $nominal): bool
    {
        $batas = (int) config('koin.batas_tarik_2hari');
        return abs($this->totalTarikDuaHari($santri)) + $nominal <= $batas;
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
