<?php

namespace App\Http\Controllers\Api;

use App\Exports\TransactionsExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TransactionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15) ?: 15, (int) config('koin.max_per_page'));

        $query = Transaction::with('santri:id,nis,nama', 'creator:id,name')
            ->when($request->input('kategori'), fn ($q, $kategori) => $q->where('tipe', $kategori))
            ->when($request->input('dari'), fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($request->input('sampai'), fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('santri', fn ($q) => $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")));
            })
            ->latest('created_at');

        return TransactionResource::collection($query->paginate($perPage));
    }

    public function show(Transaction $transaction): TransactionResource
    {
        return new TransactionResource($transaction->load('santri', 'creator'));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Transaction::with('santri:id,nis,nama', 'creator:id,name')
            ->when($request->input('kategori'), fn ($q, $kategori) => $q->where('tipe', $kategori))
            ->when($request->input('dari'), fn ($q, $dari) => $q->whereDate('created_at', '>=', $dari))
            ->when($request->input('sampai'), fn ($q, $sampai) => $q->whereDate('created_at', '<=', $sampai))
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('keterangan', 'like', "%{$search}%")
                    ->orWhereHas('santri', fn ($q) => $q->where('nama', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")));
            })
            ->orderBy('created_at', 'desc');

        return Excel::download(new TransactionsExport($query), 'transaksi-' . now()->format('Y-m-d') . '.xlsx');
    }
}
