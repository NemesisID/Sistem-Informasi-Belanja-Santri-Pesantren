<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $totalSaldo = (int) Santri::sum('saldo');
        $totalPemasukan = (int) Transaction::where('tipe', 'topup')->sum('nominal');
        $totalPenarikan = (int) abs(Transaction::where('tipe', 'tarik_koin')->sum('nominal'));
        $totalTransaksi = (int) Transaction::count();

        // Tren 14 hari terakhir
        $tren = collect(range(13, 0))->map(function ($daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'tanggal' => $date,
                'pemasukan' => (int) Transaction::whereDate('created_at', $date)->where('tipe', 'topup')->sum('nominal'),
                'penarikan' => (int) abs(Transaction::whereDate('created_at', $date)->where('tipe', 'tarik_koin')->sum('nominal')),
            ];
        });

        $terbaru = Transaction::with('santri:id,nis,nama', 'creator:id,name')
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Transaction $t) => [
                'id' => $t->id,
                'tipe' => $t->tipe,
                'nominal' => (int) $t->nominal,
                'keterangan' => $t->keterangan,
                'created_at' => $t->created_at?->toIso8601String(),
                'santri' => $t->santri ? ['id' => $t->santri->id, 'nis' => $t->santri->nis, 'nama' => $t->santri->nama] : null,
            ]);

        return response()->json([
            'total_saldo' => $totalSaldo,
            'total_pemasukan' => $totalPemasukan,
            'total_penarikan' => $totalPenarikan,
            'total_transaksi' => $totalTransaksi,
            'tren_transaksi' => $tren,
            'transaksi_terbaru' => $terbaru,
        ]);
    }
}
