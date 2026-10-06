<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Halaman monitoring: kondisi aplikasi, database, disk, versi paket aktif,
 * dan ringkasan aktivitas harian. Database mati -> HTTP 503 supaya layanan
 * uptime eksternal (mis. UptimeRobot) bisa memberi peringatan.
 *
 * Diproteksi middleware EnsureAccessKey (query ?key= / header X-Access-Key).
 * Tambahkan ?json=1 untuk format JSON.
 */
class StatusController extends Controller
{
    public function __invoke(Request $request)
    {
        $dbOk = true;
        $dbInfo = 'terhubung';

        try {
            DB::select('select 1');
        } catch (Throwable $e) {
            $dbOk = false;
            $dbInfo = 'GAGAL: ' . $e->getMessage();
        }

        $data = [
            'ok' => $dbOk,
            'paket' => trim((string) @file_get_contents(base_path('version.txt'))) ?: 'tidak diketahui',
            'aplikasi' => config('app.name'),
            'lingkungan' => app()->environment(),
            'php' => PHP_VERSION,
            'database' => $dbInfo,
            'zona_waktu' => config('app.timezone'),
            'waktu_server' => now()->format('Y-m-d H:i:s'),
            'disk_bebas' => $this->diskBebas(),
            'santri' => $dbOk ? $this->hitung('santris') : null,
            'transaksi_hari_ini' => $dbOk ? $this->hitungTransaksiHariIni() : null,
            'backup_terakhir' => $this->backupTerakhir(),
        ];

        if (! $dbOk) {
            return response()->json($data, 503);
        }

        if ($request->boolean('json')) {
            return response()->json($data);
        }

        return response()->view('status', $data);
    }

    private function hitung(string $table): ?int
    {
        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function hitungTransaksiHariIni(): ?int
    {
        try {
            return DB::table('transactions')->whereDate('created_at', today())->count();
        } catch (Throwable) {
            return null;
        }
    }

    private function diskBebas(): string
    {
        $free = @disk_free_space(storage_path());

        return $free ? number_format($free / 1048576) . ' MB' : 'tidak diketahui';
    }

    private function backupTerakhir(): string
    {
        $dir = dirname(base_path()) . '/backups';
        $files = is_dir($dir) ? glob($dir . '/nata-*.sql.gz') : [];

        if ($files === []) {
            return 'belum ada';
        }

        $latest = max($files);

        return basename($latest) . ' (' . date('d M Y H:i', (int) filemtime($latest)) . ')';
    }
}
