<?php

namespace App\Services;

use App\Models\Santri;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

/**
 * Import data santri dari file Excel (6 sheet = 6 unit) sesuai pipeline §8.
 * Foto: cari file bernama {nis}.jpg di folder lokal lalu salin ke storage.
 */
class SantriImportService
{
    /**
     * Parse Excel dan kembalikan array data santri untuk di-preview/diedit admin sebelum disimpan.
     */
    public function parsePreview(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Exception $e) {
            $reader = new Xlsx();
            $spreadsheet = $reader->load($path);
        }

        $items = [];
        $existingNises = Santri::pluck('nis')->flip()->toArray();

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $unit = $this->unitDariNamaSheet($sheet->getTitle());
            $rows = $sheet->toArray();

            if (empty($rows)) {
                continue;
            }

            // Menggabungkan 5 baris pertama menjadi satu 'super header' per kolom
            // Karena beberapa template sekolah menaruh teks header utama (seperti VA JAJAN) di baris ke-4
            $header = [];
            for ($i = 0; $i < 5; $i++) {
                if (!isset($rows[$i])) break;
                foreach ($rows[$i] as $colIndex => $val) {
                    $header[$colIndex] = ($header[$colIndex] ?? '') . ' ' . $val;
                }
            }
            $cols = $this->petaKolom($header);

            foreach ($rows as $rowIndex => $row) {
                // Lewati baris yang kemungkinan besar baris header tabel
                if ($rowIndex < 5) {
                    $rowStr = strtolower(implode(' ', array_filter(array_map('strval', $row))));
                    if (str_contains($rowStr, 'nomor identitas') || str_contains($rowStr, 'nama santri') || str_contains($rowStr, 'jenis kelamin')) {
                        continue;
                    }
                }

                $nis = $this->bersihkan($row[$cols['nis']] ?? null);
                if ($nis === null || (string) $nis === '0' || (string) $nis === '') {
                    continue;
                }

                $nisStr = trim((string) $nis);
                if (strtolower($nisStr) === 'nis' || strtolower($nisStr) === 'no' || str_contains(strtolower($nisStr), 'identitas')) {
                    continue;
                }

                $nama = (string) ($this->bersihkan($row[$cols['nama']] ?? null) ?? '');
                if (empty($nama) || strtolower($nama) === 'nama' || strtolower($nama) === 'nama santri') {
                    continue;
                }

                $items[] = [
                    'temp_id' => 'tmp_' . $nisStr . '_' . uniqid(),
                    'nis' => $nisStr,
                    'nis2' => $this->nullable($row[$cols['nis2']] ?? null) ?? '',
                    'nama' => $nama,
                    'tempat_lahir' => $this->nullable($row[$cols['tempat_lahir']] ?? null) ?? '',
                    'tanggal_lahir' => $this->tanggal($row[$cols['tanggal_lahir']] ?? null) ?? '',
                    'jenis_kelamin' => strtoupper((string) ($this->bersihkan($row[$cols['jenis_kelamin']] ?? null) ?: 'L')) === 'P' ? 'P' : 'L',
                    'alamat' => $this->nullable($row[$cols['alamat']] ?? null) ?? '',
                    'kelas' => $this->nullable($row[$cols['kelas']] ?? null) ?? '',
                    'unit' => $unit,
                    'va_jajan' => $this->nullable($row[$cols['va_jajan']] ?? null) ?? '',
                    'status' => 'aktif',
                    'is_exists' => isset($existingNises[$nisStr]),
                ];
            }
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'total' => count($items),
            'items' => $items,
        ];
    }

    /**
     * Simpan data hasil preview/edit santri ke database secara batch.
     */
    public function confirmImport(array $items): array
    {
        return DB::transaction(function () use ($items) {
            $report = [
                'diproses' => 0,
                'ditambah' => 0,
                'diupdate' => 0,
                'error' => 0,
            ];

            foreach ($items as $item) {
                $nis = trim((string) ($item['nis'] ?? ''));
                $nama = trim((string) ($item['nama'] ?? ''));

                if (empty($nis) || empty($nama)) {
                    $report['error']++;
                    continue;
                }

                $report['diproses']++;

                $data = [
                    'nis' => $nis,
                    'nis2' => !empty($item['nis2']) ? (string) $item['nis2'] : null,
                    'nama' => $nama,
                    'tempat_lahir' => !empty($item['tempat_lahir']) ? (string) $item['tempat_lahir'] : null,
                    'tanggal_lahir' => !empty($item['tanggal_lahir']) ? (string) $item['tanggal_lahir'] : null,
                    'jenis_kelamin' => strtoupper((string) ($item['jenis_kelamin'] ?? 'L')) === 'P' ? 'P' : 'L',
                    'alamat' => !empty($item['alamat']) ? (string) $item['alamat'] : null,
                    'kelas' => !empty($item['kelas']) ? (string) $item['kelas'] : null,
                    'unit' => !empty($item['unit']) ? (string) $item['unit'] : 'BARU',
                    'va_jajan' => !empty($item['va_jajan']) ? (string) $item['va_jajan'] : null,
                    'status' => in_array($item['status'] ?? 'aktif', ['aktif', 'nonaktif']) ? $item['status'] : 'aktif',
                ];

                $exists = Santri::where('nis', $nis)->exists();
                $santri = Santri::updateOrCreate(['nis' => $nis], $data);
                $santri->syncWaliAccount();

                if ($exists) {
                    $report['diupdate']++;
                } else {
                    $report['ditambah']++;
                }
            }

            return $report;
        });
    }

    public function import(string|UploadedFile $file, ?string $fotoFolder = null): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        
        try {
            $spreadsheet = IOFactory::load($path);
        } catch (\Exception $e) {
            $reader = new Xlsx();
            $spreadsheet = $reader->load($path);
        }

        $report = DB::transaction(function () use ($spreadsheet, $fotoFolder) {
            $report = [
                'diproses' => 0,
                'ditambah' => 0,
                'diupdate' => 0,
                'foto_terpasang' => 0,
                'error' => 0,
            ];

            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $unit = $this->unitDariNamaSheet($sheet->getTitle());
                $rows = $sheet->toArray();
                
                if (empty($rows)) {
                    continue;
                }

                $header = [];
                for ($i = 0; $i < 5; $i++) {
                    if (!isset($rows[$i])) break;
                    foreach ($rows[$i] as $colIndex => $val) {
                        $header[$colIndex] = ($header[$colIndex] ?? '') . ' ' . $val;
                    }
                }
                $cols = $this->petaKolom($header);

                foreach ($rows as $rowIndex => $row) {
                    if ($rowIndex < 5) {
                        $rowStr = strtolower(implode(' ', array_filter(array_map('strval', $row))));
                        if (str_contains($rowStr, 'nomor identitas') || str_contains($rowStr, 'nama santri') || str_contains($rowStr, 'jenis kelamin')) {
                            continue;
                        }
                    }

                    $nis = $this->bersihkan($row[$cols['nis']] ?? null);
                    if ($nis === null || (string) $nis === '0' || (string) $nis === '') {
                        continue;
                    }

                    $nisStr = trim((string) $nis);
                    if (strtolower($nisStr) === 'nis' || strtolower($nisStr) === 'no' || str_contains(strtolower($nisStr), 'identitas')) {
                        continue;
                    }

                    $nama = (string) ($this->bersihkan($row[$cols['nama']] ?? null) ?? '');
                    if (empty($nama) || strtolower($nama) === 'nama' || strtolower($nama) === 'nama santri') {
                        continue;
                    }

                    $report['diproses']++;

                    $data = [
                        'nis' => $nisStr,
                        'nis2' => $this->nullable($row[$cols['nis2']] ?? null),
                        'nama' => $nama,
                        'tempat_lahir' => $this->nullable($row[$cols['tempat_lahir']] ?? null),
                        'tanggal_lahir' => $this->tanggal($row[$cols['tanggal_lahir']] ?? null),
                        'jenis_kelamin' => strtoupper((string) ($this->bersihkan($row[$cols['jenis_kelamin']] ?? null) ?: 'L')),
                        'alamat' => $this->nullable($row[$cols['alamat']] ?? null),
                        'kelas' => $this->nullable($row[$cols['kelas']] ?? null),
                        'unit' => $unit,
                        'va_jajan' => $this->nullable($row[$cols['va_jajan']] ?? null),
                        'status' => 'aktif',
                        'saldo' => 0,
                    ];

                    $exists = Santri::where('nis', $nisStr)->exists();
                    $santri = Santri::updateOrCreate(['nis' => $nisStr], $data);
                    $santri->syncWaliAccount();

                    if ($exists) {
                        $report['diupdate']++;
                    } else {
                        $report['ditambah']++;
                    }

                    if ($fotoFolder && $this->pasangFoto($nisStr, $fotoFolder)) {
                        $report['foto_terpasang']++;
                    }
                }
            }

            return $report;
        });

        $spreadsheet->disconnectWorksheets();

        return $report;
    }

    /** Unit dari nama sheet: SANTRI BARU → BARU, sisanya nama sheet asli. */
    private function unitDariNamaSheet(string $nama): string
    {
        $unit = strtoupper(trim($nama));
        return $unit === 'SANTRI BARU' ? 'BARU' : $unit;
    }

    /** Peta indeks kolom berdasarkan nama header (fleksibel dengan berbagai variasi alias). */
    private function petaKolom(array $header): array
    {
        $map = [
            'nis' => ['nomor identitas 1', 'no identitas 1', 'no. identitas 1', 'no induk', 'nomor induk', 'no_induk', 'id santri', 'nis'],
            'nis2' => ['nomor identitas 2', 'no identitas 2', 'no. identitas 2', 'nis2'],
            'nama' => ['nama santri', 'nama lengkap', 'nama siswa', 'nama'],
            'tempat_lahir' => ['tempat lahir', 'tmp lahir', 'tempat_lahir', 'tpt lahir', 'kota lahir'],
            'tanggal_lahir' => ['tanggal lahir', 'tgl lahir', 'tanggal_lahir', 'tgl_lahir', 'tgl'],
            'jenis_kelamin' => ['jenis kelamin', 'jenis_kelamin', 'kelamin', 'jk', 'gender', 'l/p', 'sex'],
            'alamat' => ['alamat lengkap', 'alamat santri', 'alamat', 'domisili'],
            'kelas' => ['kelas', 'kls', 'tingkat', 'kelas santri'],
            'va_jajan' => ['va jajan', 'virtual account jajan', 'va_jajan', 'va tagihan', 'va', 'no va', 'nomor va'],
        ];

        $index = array_fill_keys(array_keys($map), null);
        
        foreach ($header as $i => $h) {
            $key = strtolower(trim((string) $this->bersihkan($h)));
            if (empty($key)) continue;

            foreach ($map as $field => $patterns) {
                if ($index[$field] !== null) continue; // Sudah terpetakan
                
                foreach ((array) $patterns as $pattern) {
                    if ($key === $pattern || str_contains($key, $pattern) || str_contains($pattern, $key)) {
                        $index[$field] = $i;
                        break;
                    }
                }
            }
        }

        // Fallback jika kolom utama belum terpetakan
        if ($index['nis'] === null && isset($header[0])) {
            $index['nis'] = 0;
        }
        if ($index['nama'] === null && isset($header[1])) {
            $index['nama'] = 1;
        }

        return $index;
    }

    private function tanggal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (\Exception $e) {
                // Lanjut
            }
        }

        $value = trim((string) $value);
        
        // Terjemahkan nama bulan Indonesia ke Inggris
        $bulanId = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember', 'agu', 'okt', 'des'];
        $bulanEn = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december', 'aug', 'oct', 'dec'];
        $valueStr = str_ireplace($bulanId, $bulanEn, $value);

        // 1. Coba strtotime langsung tanpa normalisasi
        $parsedRaw = strtotime($valueStr);
        if ($parsedRaw) {
            return date('Y-m-d', $parsedRaw);
        }

        // 2. Normalisasi semua separator menjadi tanda hubung (-)
        $normalized = preg_replace('/[\s\/\.]+/', '-', $valueStr);
        $parsed = strtotime($normalized);
        if ($parsed) {
            return date('Y-m-d', $parsed);
        }

        // 3. Fallback Carbon terakhir dengan format Indonesia jika bentuknya aneh
        $formats = ['d M Y', 'd F Y', 'Y-m-d', 'Y/m/d'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $valueStr)->format('Y-m-d');
            } catch (\Exception $e) {
                // Lanjut
            }
        }
        
        return null;
    }

    /** Normalisasi '0.0'/kosong/strip → null. */
    private function nullable(mixed $value): ?string
    {
        $clean = $this->bersihkan($value);
        if ($clean === null || (string) $clean === '0' || (string) $clean === '0.0' || (string) $clean === '-') {
            return null;
        }
        return (string) $clean;
    }

    private function bersihkan(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    /** Salin file foto {nis}.* dari folder lokal ke storage publik. */
    private function pasangFoto(string $nis, string $folder): bool
    {
        $files = glob(rtrim($folder, '/\\')."/{$nis}.*");
        if (! $files) {
            return false;
        }

        $ext = strtolower(pathinfo($files[0], PATHINFO_EXTENSION));
        $target = "santri-foto/{$nis}.{$ext}";
        Storage::disk('public')->put($target, file_get_contents($files[0]));
        Santri::where('nis', $nis)->update(['foto_path' => $target]);

        return true;
    }
}
