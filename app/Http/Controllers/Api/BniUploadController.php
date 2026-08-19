<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BniUpload;
use App\Models\BniUploadItem;
use App\Services\BniImportService;
use App\Services\SaldoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BniUploadController extends Controller
{
    public function __construct(
        private readonly BniImportService $importer,
        private readonly SaldoService $saldo,
    ) {
    }

    /** Upload mutasi → parse + cocok VA → preview. */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'extensions:xlsx,xls,csv'],
        ]);

        $file = $request->file('file');
        $parsed = $this->importer->parse($file);

        if (count($parsed['items']) === 0) {
            throw ValidationException::withMessages([
                'file' => ['Tidak ada data mutasi yang terbaca dari file.'],
            ]);
        }

        $upload = DB::transaction(function () use ($file, $parsed, $request) {
            $path = $file->store('bni-uploads', 'local');

            $upload = BniUpload::create([
                'nama_file' => $file->getClientOriginalName(),
                'path' => $path,
                'status' => 'menunggu',
                'jumlah_total' => count($parsed['items']),
                'jumlah_valid' => $parsed['valid'],
                'jumlah_invalid' => $parsed['invalid'],
                'uploaded_by' => $request->user()->id,
            ]);

            foreach ($parsed['items'] as $item) {
                BniUploadItem::create($item + ['upload_id' => $upload->id]);
            }

            return $upload;
        });

        return response()->json([
            'message' => 'File diproses. Silakan cek preview sebelum apply.',
            'upload' => $this->detail($upload),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $uploads = BniUpload::with('uploader:id,name')
            ->latest('created_at')
            ->paginate(min((int) $request->integer('per_page', 15) ?: 15, (int) config('koin.max_per_page')));

        return response()->json($uploads->through(fn (BniUpload $u) => $this->detail($u)));
    }

    public function show(BniUpload $upload): JsonResponse
    {
        return response()->json($this->detail($upload));
    }

    /**
     * Kredit saldo item valid (atau hanya item_ids yang dipilih) dalam satu transaksi DB (atomik).
     */
    public function apply(Request $request, BniUpload $upload): JsonResponse
    {
        if ($upload->status !== 'menunggu') {
            throw ValidationException::withMessages([
                'upload' => ['Upload ini sudah diproses (status: '.$upload->status.').'],
            ]);
        }

        $selectedItemIds = $request->input('item_ids');
        $dikredit = 0;

        DB::transaction(function () use ($upload, $request, $selectedItemIds, &$dikredit) {
            // Kunci baris upload agar tidak di-apply bersamaan dua kali
            $locked = BniUpload::whereKey($upload->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'menunggu') {
                throw ValidationException::withMessages([
                    'upload' => ['Upload ini sudah diproses.'],
                ]);
            }

            $query = $locked->items()->where('status_valid', true)->where('diterapkan', false);
            if (is_array($selectedItemIds) && count($selectedItemIds) > 0) {
                $query->whereIn('id', $selectedItemIds);
            }
            $items = $query->get();

            foreach ($items as $item) {
                // Cegah double-credit bila transaksi yang sama (journal/key) sudah dikredit di upload lain
                $sudahDikredit = $item->dedup_key !== null
                    && BniUploadItem::where('dedup_key', $item->dedup_key)
                        ->where('diterapkan', true)
                        ->where('id', '!=', $item->id)
                        ->exists();

                if ($sudahDikredit) {
                    $item->update([
                        'status_valid' => false,
                        'diterapkan' => true,
                        'applied_at' => now(),
                        'catatan' => 'Duplikat: transaksi sama sudah dikredit di upload lain.',
                    ]);
                    continue;
                }

                $this->saldo->kredit(
                    $item->santri,
                    (int) $item->nominal,
                    'topup',
                    $request->user(),
                    "Top-up VA {$item->va} (upload #{$locked->id})",
                    $item->id,
                );
                $dikredit++;

                $item->update(['diterapkan' => true, 'applied_at' => now()]);
            }

            $locked->update(['status' => 'terapkan']);
        });

        return response()->json([
            'message' => "Saldo berhasil dikredit dari {$dikredit} item valid.",
        ]);
    }

    /** Update item sebelum disimpan (misal perbaiki nominal/nama/santri_id) */
    public function updateItem(Request $request, BniUpload $upload, BniUploadItem $item): JsonResponse
    {
        if ($upload->status !== 'menunggu') {
            return response()->json(['message' => 'Upload sudah diproses, item tidak dapat diedit.'], 422);
        }

        $validated = $request->validate([
            'nama' => ['nullable', 'string', 'max:255'],
            'nominal' => ['nullable', 'numeric', 'min:1'],
            'santri_id' => ['nullable', 'exists:santris,id'],
            'status_valid' => ['nullable', 'boolean'],
        ]);

        if (isset($validated['santri_id']) && $validated['santri_id']) {
            $validated['status_valid'] = true;
            $validated['catatan'] = null;
        }

        $item->update($validated);

        // Update counts
        $upload->update([
            'jumlah_valid' => $upload->items()->where('status_valid', true)->count(),
            'jumlah_invalid' => $upload->items()->where('status_valid', false)->count(),
        ]);

        return response()->json([
            'message' => 'Item berhasil diperbarui.',
            'item' => $item->load('santri:id,nis,nama'),
        ]);
    }

    /** Hapus item dari preview sebelum disimpan */
    public function destroyItem(BniUpload $upload, BniUploadItem $item): JsonResponse
    {
        if ($upload->status !== 'menunggu') {
            return response()->json(['message' => 'Upload sudah diproses, item tidak dapat dihapus.'], 422);
        }

        $item->delete();

        // Update counts
        $upload->update([
            'jumlah_total' => $upload->items()->count(),
            'jumlah_valid' => $upload->items()->where('status_valid', true)->count(),
            'jumlah_invalid' => $upload->items()->where('status_valid', false)->count(),
        ]);

        return response()->json([
            'message' => 'Item berhasil dihapus.',
        ]);
    }

    /** Shape detail untuk preview (tabel UploadBNIPage). */
    private function detail(BniUpload $upload): array
    {
        $items = $upload->items()->with('santri:id,nis,nama')->get();

        return [
            'id' => $upload->id,
            'nama_file' => $upload->nama_file,
            'status' => $upload->status,
            'jumlah_total' => $upload->jumlah_total,
            'jumlah_valid' => $upload->jumlah_valid,
            'jumlah_invalid' => $upload->jumlah_invalid,
            'created_at' => $upload->created_at?->toIso8601String(),
            'uploaded_by' => $upload->uploader?->name,
            'items' => $items->map(fn (BniUploadItem $i) => [
                'id' => $i->id,
                'va' => $i->va,
                'nama' => $i->nama,
                'nominal' => (int) $i->nominal,
                'tanggal' => $i->tanggal?->format('Y-m-d'),
                'journal' => $i->journal,
                'billing_id' => $i->billing_id,
                'status_valid' => (bool) $i->status_valid,
                'catatan' => $i->catatan,
                'diterapkan' => (bool) $i->diterapkan,
                'santri' => $i->santri ? ['id' => $i->santri->id, 'nis' => $i->santri->nis, 'nama' => $i->santri->nama] : null,
            ]),
        ];
    }
}
