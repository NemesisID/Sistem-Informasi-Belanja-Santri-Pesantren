<?php

namespace App\Http\Controllers\Api;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Models\Santri;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WaliController extends Controller
{
    /**
     * Dashboard wali: daftar anak (santri terpaut) + saldo, VA, statistik.
     * ?santri_id= fokus pada satu anak.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $children = Auth::user()->santris()->orderBy('nama')->get();

        $selected = $children->firstWhere('id', $request->integer('santri_id')) ?? $children->first();

        $stats = ['total_isi' => 0, 'total_tarik' => 0];

        if ($selected) {
            $stats['total_isi'] = (int) Transaction::where('santri_id', $selected->id)->where('nominal', '>', 0)->sum('nominal');
            $stats['total_tarik'] = (int) abs(Transaction::where('santri_id', $selected->id)->where('nominal', '<', 0)->sum('nominal'));
        }

        return response()->json([
            'santris' => $children->map(fn (Santri $s) => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'unit' => $s->unit,
            ]),
            'santri_terpilih' => $selected ? [
                'id' => $selected->id,
                'nis' => $selected->nis,
                'nama' => $selected->nama,
                'unit' => $selected->unit,
                'saldo' => (int) $selected->saldo,
                'va_jajan' => $selected->va_jajan,
            ] : null,
            'statistik' => $stats,
        ]);
    }

    /** Riwayat transaksi anak-anak wali, filter tipe masuk/keluar/semua. */
    public function transactions(Request $request): JsonResponse
    {
        $childIds = Auth::user()->santris()->pluck('santris.id');

        $query = Transaction::with('santri:id,nis,nama')
            ->whereIn('santri_id', $childIds)
            ->when($request->input('tipe') === 'masuk', fn ($q) => $q->where('nominal', '>', 0))
            ->when($request->input('tipe') === 'keluar', fn ($q) => $q->where('nominal', '<', 0))
            ->when($request->input('dari'), fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($request->input('sampai'), fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->latest('created_at');

        $perPage = min((int) $request->integer('per_page', 15) ?: 15, (int) config('koin.max_per_page'));

        return response()->json($query->paginate($perPage)->through(fn (Transaction $t) => [
            'id' => $t->id,
            'tipe' => $t->tipe,
            'nominal' => (int) $t->nominal,
            'saldo_sebelum' => (int) $t->saldo_sebelum,
            'saldo_setelah' => (int) $t->saldo_setelah,
            'keterangan' => $t->keterangan,
            'created_at' => $t->created_at?->toIso8601String(),
            'santri' => $t->santri ? ['id' => $t->santri->id, 'nis' => $t->santri->nis, 'nama' => $t->santri->nama] : null,
        ]));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $childIds = Auth::user()->santris()->pluck('santris.id');

        $query = Transaction::whereIn('santri_id', $childIds)
            ->when($request->input('tipe') === 'masuk', fn ($q) => $q->where('nominal', '>', 0))
            ->when($request->input('tipe') === 'keluar', fn ($q) => $q->where('nominal', '<', 0))
            ->when($request->input('dari'), fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($request->input('sampai'), fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->orderBy('created_at');

        return Excel::download(new TransactionsExport($query), 'riwayat-transaksi.xlsx');
    }
}
