<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PenyesuaianRequest;
use App\Http\Requests\SantriRequest;
use App\Http\Resources\SantriResource;
use App\Http\Resources\TransactionResource;
use App\Models\Santri;
use App\Services\SaldoService;
use App\Services\SantriImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SantriController extends Controller
{
    public function __construct(
        private readonly SaldoService $saldo,
        private readonly SantriImportService $importer,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15) ?: 15, (int) config('koin.max_per_page'));

        $query = Santri::query()
            ->when($request->input('search'), function ($q, $search) {
                $q->where(fn ($q) => $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%"));
            })
            ->when($request->input('unit'), fn ($q, $unit) => $q->where('unit', $unit))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('nis');

        return SantriResource::collection($query->paginate($perPage));
    }

    /**
     * Pencarian cepat untuk verifikasi penarikan koin di loket.
     * Wajib menyertakan foto_url.
     */
    public function byNis(Request $request): JsonResponse
    {
        $nis = trim((string) $request->input('nis'));

        if ($nis === '') {
            throw ValidationException::withMessages(['nis' => ['Parameter nis wajib diisi.']]);
        }

        $santri = Santri::where('nis', $nis)->first();

        if (! $santri) {
            throw ValidationException::withMessages(['nis' => ['Santri dengan NIS tersebut tidak ditemukan.']]);
        }

        return (new SantriResource($santri))->response();
    }

    public function show(Santri $santri): SantriResource
    {
        return new SantriResource($santri);
    }

    public function store(SantriRequest $request): JsonResponse
    {
        $data = $request->validated();
        unset($data['foto']);

        if ($request->hasFile('foto')) {
            $slug = Str::random(12);
            $data['foto_path'] = $this->simpanFoto($request, (string) $request->input('nis'));
            $data['foto_slug'] = $slug;
        }

        $santri = Santri::create($data);
        $santri->syncWaliAccount();

        return (new SantriResource($santri))->response()->setStatusCode(201);
    }

    public function update(SantriRequest $request, Santri $santri): SantriResource
    {
        $data = $request->validated();
        unset($data['foto']);

        if ($request->hasFile('foto')) {
            if ($santri->foto_path) {
                Storage::disk('local')->delete($santri->foto_path);
            }
            $data['foto_path'] = $this->simpanFoto($request, $santri->nis);
            $data['foto_slug'] = Str::random(12);
        }

        $santri->update($data);
        $santri->syncWaliAccount();

        return new SantriResource($santri->fresh());
    }

    public function destroy(Santri $santri): JsonResponse
    {
        $santri->delete();

        return response()->json(['message' => 'Santri dihapus.']);
    }

    public function mutasi(Santri $santri): AnonymousResourceCollection
    {
        return TransactionResource::collection(
            $santri->transactions()->with('santri', 'creator')->latest('created_at')->get()
        );
    }

    public function penyesuaian(PenyesuaianRequest $request, Santri $santri): JsonResponse
    {
        $user = $request->user();
        $nominal = (int) $request->input('nominal');
        $keterangan = $request->input('keterangan');

        $transaction = $request->input('aksi') === 'tambah'
            ? $this->saldo->kredit($santri, $nominal, 'penyesuaian', $user, $keterangan)
            : $this->saldo->debit($santri, $nominal, 'penyesuaian', $user, $keterangan);

        return response()->json([
            'message' => 'Penyesuaian saldo berhasil.',
            'transaction' => new TransactionResource($transaction->load('santri', 'creator')),
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv'],
        ]);

        $report = $this->importer->import($request->file('file'));

        return response()->json($report);
    }

    public function importPreview(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv'],
        ]);

        $preview = $this->importer->parsePreview($request->file('file'));

        return response()->json($preview);
    }

    public function importConfirm(Request $request): JsonResponse
    {
        $request->validate([
            'items' => ['required', 'array'],
            'items.*.nis' => ['required', 'string'],
            'items.*.nama' => ['required', 'string'],
        ]);

        $report = $this->importer->confirmImport($request->input('items'));

        return response()->json($report);
    }

    private function simpanFoto(Request $request, string $nis): string
    {
        return $request->file('foto')->storeAs(
            'santri-foto',
            "{$nis}.{$request->file('foto')->extension()}",
            'local'
        );
    }
}
