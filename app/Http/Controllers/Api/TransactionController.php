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
        $perPage = min((int) $request->integer("per_page", 15) ?: 15, (int) config("koin.max_per_page"));

        $query = Transaction::with("santri:id,nis,nama", "creator:id,name")
            ->when($request->filled("tipe"), function ($q) use ($request) {
                $t = strtolower($request->input("tipe"));
                if ($t === "masuk") {
                    $q->where("nominal", ">", 0);
                } elseif ($t === "keluar") {
                    $q->where("nominal", "<", 0);
                } else {
                    $q->where("tipe", $t);
                }
            })
            ->when($request->filled("kategori") && !$request->filled("tipe"), function ($q) use ($request) {
                $k = strtolower($request->input("kategori"));
                if ($k === "masuk") {
                    $q->where("nominal", ">", 0);
                } elseif ($k === "keluar") {
                    $q->where("nominal", "<", 0);
                } else {
                    $q->where("tipe", $k);
                }
            })
            ->when($request->filled("dari"), fn ($q) => $q->whereDate("created_at", ">=", $request->input("dari")))
            ->when($request->filled("sampai"), fn ($q) => $q->whereDate("created_at", "<=", $request->input("sampai")))
            ->when($request->filled("search"), function ($q) use ($request) {
                $search = $request->input("search");
                $q->where(fn ($q) => $q->where("keterangan", "like", "%{$search}%")
                    ->orWhereHas("santri", fn ($q) => $q->where("nama", "like", "%{$search}%")
                        ->orWhere("nis", "like", "%{$search}%")));
            })
            ->latest("created_at");

        return TransactionResource::collection($query->paginate($perPage));
    }

    public function show(Transaction $transaction): TransactionResource
    {
        return new TransactionResource($transaction->load("santri", "creator"));
    }

    public function export(Request $request): BinaryFileResponse
    {
        $query = Transaction::with("santri:id,nis,nama", "creator:id,name")
            ->when($request->filled("tipe"), function ($q) use ($request) {
                $t = strtolower($request->input("tipe"));
                if ($t === "masuk") {
                    $q->where("nominal", ">", 0);
                } elseif ($t === "keluar") {
                    $q->where("nominal", "<", 0);
                } else {
                    $q->where("tipe", $t);
                }
            })
            ->when($request->filled("kategori") && !$request->filled("tipe"), function ($q) use ($request) {
                $k = strtolower($request->input("kategori"));
                if ($k === "masuk") {
                    $q->where("nominal", ">", 0);
                } elseif ($k === "keluar") {
                    $q->where("nominal", "<", 0);
                } else {
                    $q->where("tipe", $k);
                }
            })
            ->when($request->filled("dari"), fn ($q) => $q->whereDate("created_at", ">=", $request->input("dari")))
            ->when($request->filled("sampai"), fn ($q) => $q->whereDate("created_at", "<=", $request->input("sampai")))
            ->when($request->filled("search"), function ($q) use ($request) {
                $search = $request->input("search");
                $q->where(fn ($q) => $q->where("keterangan", "like", "%{$search}%")
                    ->orWhereHas("santri", fn ($q) => $q->where("nama", "like", "%{$search}%")
                        ->orWhere("nis", "like", "%{$search}%")));
            })
            ->orderBy("created_at", "desc");

        return Excel::download(new TransactionsExport($query), "transaksi-" . now()->format("Y-m-d") . ".xlsx");
    }
}