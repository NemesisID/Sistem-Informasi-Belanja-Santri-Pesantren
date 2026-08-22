<?php

namespace App\Http\Controllers\Api;

use App\Exports\FinancialReportExport;
use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function financial(Request $request): JsonResponse
    {
        $month = $request->input('bulan', now()->format('Y-m'));

        try {
            $start = Carbon::parse($month.'-01')->startOfMonth();
            $end = Carbon::parse($month.'-01')->endOfMonth();
        } catch (\Throwable) {
            return response()->json(['message' => 'Format bulan tidak valid. Gunakan YYYY-MM.', 'errors' => ['bulan' => []]], 422);
        }

        $query = Transaction::with('santri:id,nis,nama', 'creator:id,name')
            ->whereBetween('created_at', [$start, $end]);

        $pemasukan = (int) (clone $query)->where('nominal', '>', 0)->sum('nominal');
        $pengeluaran = (int) abs((clone $query)->where('nominal', '<', 0)->sum('nominal'));
        $saldoAkhir = (int) Transaction::where('created_at', '<=', $end)->sum('nominal');

        $detail = $query->latest('created_at')->paginate((int) config('koin.max_per_page'));

        return response()->json([
            'bulan' => $month,
            'pemasukan' => $pemasukan,
            'pengeluaran' => $pengeluaran,
            'saldo_akhir' => $saldoAkhir,
            'detail' => $detail,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        $month = $request->input('bulan', now()->format('Y-m'));

        try {
            Carbon::parse($month.'-01');
        } catch (\Throwable) {
            return response()->json(['message' => 'Format bulan tidak valid. Gunakan YYYY-MM.', 'errors' => ['bulan' => []]], 422);
        }

        // Export laporan keuangan format formal resmi sesuai standar LPJ Pesantren (persis format PDF)
        return Excel::download(new FinancialReportExport($month), "laporan-keuangan-{$month}.xlsx");
    }

    /** Ringkasan pemasukan/pengeluaran 12 bulan terakhir untuk grafik dan rekap laporan. */
    public function summary(): JsonResponse
    {
        $months = collect(range(0, 11))->map(function ($i) {
            $period = now()->subMonths($i);
            $start  = $period->copy()->startOfMonth();
            $end    = $period->copy()->endOfMonth();

            $txQuery = Transaction::whereBetween('created_at', [$start, $end]);

            $pemasukan   = (int) (clone $txQuery)->where('nominal', '>', 0)->sum('nominal');
            $pengeluaran = (int) abs((clone $txQuery)->where('nominal', '<', 0)->sum('nominal'));
            $jmlTrx      = (int) (clone $txQuery)->count();

            $staffIds   = (clone $txQuery)->whereNotNull('created_by')->distinct()->pluck('created_by');
            $staffNames = \App\Models\User::whereIn('id', $staffIds)->pluck('name')->all();
            $staff      = !empty($staffNames) ? implode(', ', $staffNames) : 'Administrator, Staff Rumah Koin';

            return [
                'bulan'            => $period->format('Y-m'),
                'label'            => $period->translatedFormat('F Y'),
                'pemasukan'        => $pemasukan,
                'pengeluaran'      => $pengeluaran,
                'net'              => $pemasukan - $pengeluaran,
                'jumlah_transaksi' => $jmlTrx,
                'staff'            => $staff,
                'status'           => now()->format('Y-m') === $period->format('Y-m') ? 'Berjalan' : 'Selesai',
            ];
        });

        // Hanya bulan yang punya transaksi (masuk/keluar) — bulan kosong disembunyikan.
        return response()->json([
            'data' => $months->filter(fn (array $m) => $m['jumlah_transaksi'] > 0 || $m['pemasukan'] !== 0 || $m['pengeluaran'] !== 0)->values(),
        ]);
    }
}
