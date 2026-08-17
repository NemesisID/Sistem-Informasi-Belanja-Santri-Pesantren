<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PenarikanRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Santri;
use App\Services\SaldoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class PenarikanController extends Controller
{
    public function __construct(private readonly SaldoService $saldo)
    {
    }

    /**
     * Penarikan koin di loket Rumah Koin.
     * Wajib: foto santri sudah ada (verifikasi identitas), nominal dalam batas 2 hari,
     * saldo cukup. Semua dalam satu transaksi DB.
     */
    public function store(PenarikanRequest $request): JsonResponse
    {
        $santri = Santri::findOrFail($request->input('santri_id'));

        if ($santri->status !== 'aktif') {
            throw ValidationException::withMessages([
                'santri_id' => ['Santri nonaktif tidak bisa menarik koin.'],
            ]);
        }

        $transaction = $this->saldo->withdrawal($santri, (int) $request->input('nominal'), $request->user());

        return response()->json([
            'message' => 'Penarikan berhasil.',
            'transaction' => new TransactionResource($transaction->load('santri', 'creator')),
        ], 201);
    }
}
