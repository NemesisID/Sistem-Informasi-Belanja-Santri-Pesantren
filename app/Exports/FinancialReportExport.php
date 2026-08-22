<?php

namespace App\Exports;

use App\Models\Santri;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinancialReportExport implements FromView, ShouldAutoSize, WithTitle
{
    public function __construct(private readonly string $month)
    {
    }

    public function title(): string
    {
        return 'LPJ Keuangan ' . Carbon::parse($this->month . '-01')->format('M Y');
    }

    public function view(): View
    {
        $selectedPeriod = Carbon::parse($this->month . '-01');
        $startOfMonth = $selectedPeriod->copy()->startOfMonth();
        $endOfMonth = $selectedPeriod->copy()->endOfMonth();

        // 1. Data Ringkasan Eksekutif Bulan Terpilih
        $monthQuery = Transaction::whereBetween('created_at', [$startOfMonth, $endOfMonth]);
        $activeMonthPemasukan = (int) (clone $monthQuery)->where('nominal', '>', 0)->sum('nominal');
        $activeMonthPengeluaran = (int) abs((clone $monthQuery)->where('nominal', '<', 0)->sum('nominal'));
        $activeMonthNet = $activeMonthPemasukan - $activeMonthPengeluaran;
        $activeMonthTrx = (int) (clone $monthQuery)->count();

        // Total Saldo Simpanan Santri Aktif
        $totalSaldoAktif = (int) Santri::where('status', 'aktif')->sum('saldo');

        // 2. Data Rincian Rekapitulasi Keuangan Per Periode (12 Bulan Terakhir)
        $months = collect(range(0, 11))->map(function ($i) {
            $period = now()->subMonths($i);
            $start = $period->copy()->startOfMonth();
            $end = $period->copy()->endOfMonth();

            $txQuery = Transaction::whereBetween('created_at', [$start, $end]);

            $pemasukan = (int) (clone $txQuery)->where('nominal', '>', 0)->sum('nominal');
            $pengeluaran = (int) abs((clone $txQuery)->where('nominal', '<', 0)->sum('nominal'));
            $jmlTrx = (int) (clone $txQuery)->count();

            $staffIds = (clone $txQuery)->whereNotNull('created_by')->distinct()->pluck('created_by');
            $staffNames = User::whereIn('id', $staffIds)->pluck('name')->all();
            $staff = !empty($staffNames) ? implode(', ', $staffNames) : 'Administrator, Staff Rumah Koin';

            return [
                'bulan' => $period->format('Y-m'),
                'label' => $period->translatedFormat('F Y'),
                'pemasukan' => $pemasukan,
                'pengeluaran' => $pengeluaran,
                'net' => $pemasukan - $pengeluaran,
                'jumlah_transaksi' => $jmlTrx,
                'staff' => $staff,
            ];
        });

        // Filter hanya bulan-bulan yang memiliki mutasi transaksi (atau bulan terpilih)
        $rows = $months->filter(function ($m) {
            return $m['jumlah_transaksi'] > 0 || $m['pemasukan'] !== 0 || $m['pengeluaran'] !== 0 || $m['bulan'] === $this->month;
        })->values();

        // Jika tidak ada mutasi sama sekali, tetap tampilkan baris bulan terpilih
        if ($rows->isEmpty()) {
            $rows = collect([[
                'bulan' => $this->month,
                'label' => $selectedPeriod->translatedFormat('F Y'),
                'pemasukan' => 0,
                'pengeluaran' => 0,
                'net' => 0,
                'jumlah_transaksi' => 0,
                'staff' => 'Administrator, Staff Rumah Koin',
            ]]);
        }

        $totalMasukAll = $rows->sum('pemasukan');
        $totalKeluarAll = $rows->sum('pengeluaran');
        $totalNetAll = $totalMasukAll - $totalKeluarAll;
        $totalTrxAll = $rows->sum('jumlah_transaksi');

        // Metadata Dokumen
        $docMonthNumber = $selectedPeriod->format('n');
        $docYear = $selectedPeriod->format('Y');
        $docNumber = "LPJ/BAK-NT/{$docMonthNumber}/{$docYear}";

        $printedAt = now()->translatedFormat('l, d F Y') . ' pukul ' . now()->format('H.i') . ' WIB';
        $printedBy = auth()->user()?->name ?? 'Administrator Sistem';
        $todayDateIndo = now()->translatedFormat('l, d F Y');

        return view('exports.financial_report', [
            'activeMonthLabel' => $selectedPeriod->translatedFormat('F Y'),
            'docNumber' => $docNumber,
            'printedAt' => $printedAt,
            'printedBy' => $printedBy,
            'todayDateIndo' => $todayDateIndo,
            'totalSaldoAktif' => $totalSaldoAktif,
            'activeMonthPemasukan' => $activeMonthPemasukan,
            'activeMonthPengeluaran' => $activeMonthPengeluaran,
            'activeMonthNet' => $activeMonthNet,
            'activeMonthTrx' => $activeMonthTrx,
            'rows' => $rows,
            'totalMasukAll' => $totalMasukAll,
            'totalKeluarAll' => $totalKeluarAll,
            'totalNetAll' => $totalNetAll,
            'totalTrxAll' => $totalTrxAll,
        ]);
    }
}
