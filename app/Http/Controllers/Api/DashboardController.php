<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $selectedYear = (int) $request->input('tahun', date('Y'));

        $totalSaldo = (int) Santri::sum('saldo');
        $totalPemasukan = (int) Transaction::where('tipe', 'topup')->sum('nominal');
        $totalPenarikan = (int) abs(Transaction::where('tipe', 'tarik_koin')->sum('nominal'));
        $totalTransaksi = (int) Transaction::count();
        $santriAktif = (int) Santri::where('status', 'aktif')->count();

        // Tren 7 hari / 14 hari terakhir (Mingguan/Harian)
        $namaHari = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'
        ];

        $trenHarian = collect(range(6, 0))->map(function ($daysAgo) use ($namaHari) {
            $dt = Carbon::now()->subDays($daysAgo);
            $date = $dt->toDateString();
            $dayName = $namaHari[$dt->format('l')] ?? $dt->format('l');

            $penarikan = (int) abs(Transaction::whereDate('created_at', $date)->where('tipe', 'tarik_koin')->sum('nominal'));
            $pemasukan = (int) Transaction::whereDate('created_at', $date)->where('tipe', 'topup')->sum('nominal');
            $count = (int) Transaction::whereDate('created_at', $date)->where('tipe', 'tarik_koin')->count();

            return [
                'tanggal' => $date,
                'label' => $dayName,
                'pemasukan' => $pemasukan,
                'penarikan' => $penarikan,
                'total' => $penarikan,
                'transaksi' => $count,
            ];
        });

        // Tren Bulanan per tahun yang dipilih
        $bulanNames = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
        $trenBulanan = collect(range(1, 12))->map(function ($m) use ($selectedYear, $bulanNames) {
            $penarikan = (int) abs(
                Transaction::whereYear('created_at', $selectedYear)
                    ->whereMonth('created_at', $m)
                    ->where('tipe', 'tarik_koin')
                    ->sum('nominal')
            );
            $pemasukan = (int) Transaction::whereYear('created_at', $selectedYear)
                ->whereMonth('created_at', $m)
                ->where('tipe', 'topup')
                ->sum('nominal');
            $count = (int) Transaction::whereYear('created_at', $selectedYear)
                ->whereMonth('created_at', $m)
                ->where('tipe', 'tarik_koin')
                ->count();

            return [
                'bulan' => $m,
                'label' => $bulanNames[$m - 1],
                'pemasukan' => $pemasukan,
                'penarikan' => $penarikan,
                'total' => $penarikan,
                'transaksi' => $count,
            ];
        });

        // Tren Tahunan (5 tahun terakhir)
        $currentYear = (int) date('Y');
        $trenTahunan = collect(range($currentYear - 4, $currentYear))->map(function ($yr) {
            $penarikan = (int) abs(
                Transaction::whereYear('created_at', $yr)
                    ->where('tipe', 'tarik_koin')
                    ->sum('nominal')
            );
            $pemasukan = (int) Transaction::whereYear('created_at', $yr)
                ->where('tipe', 'topup')
                ->sum('nominal');
            $count = (int) Transaction::whereYear('created_at', $yr)
                ->where('tipe', 'tarik_koin')
                ->count();

            return [
                'tahun' => $yr,
                'label' => (string) $yr,
                'pemasukan' => $pemasukan,
                'penarikan' => $penarikan,
                'total' => $penarikan,
                'transaksi' => $count,
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
            'santri_aktif' => $santriAktif,
            'total_santri_aktif' => $santriAktif,
            'tren_transaksi' => $trenHarian,
            'tren_mingguan' => $trenHarian,
            'tren_bulanan' => $trenBulanan,
            'tren_tahunan' => $trenTahunan,
            'transaksi_terbaru' => $terbaru,
        ]);
    }
}

