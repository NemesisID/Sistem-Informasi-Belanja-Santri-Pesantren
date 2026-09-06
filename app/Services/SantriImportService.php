<?php

namespace App\Services;

use App\Models\Santri;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class SantriImportService
{
    /**
     * Memproses file Excel (.xlsx / .xls / .csv) dan opsional folder foto santri.
     * Mengembalikan ringkasan statistik hasil import per sheet.
     */
    public function import(string $filePath, ?string $fotoFolder = null): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $report = [
            'total_rows' => 0,
            'diproses' => 0,
            'ditambah' => 0,
            'diupdate' => 0,
            'error' => 0,
            'foto_terpasang' => 0,
            'sheets' => [],
        ];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $sheetName = $sheet->getTitle();
            $unit = $this->unitDariNamaSheet($sheetName);
            $rows = $sheet->toArray(null, true, true, false);

            if (count($rows) < 2) {
                continue; // Skip sheet kosong / cuma header
            }

            $header = array_map('trim', array_shift($rows));
            $cols = $this->petaKolom($header);

            $sheetStats = ['sheet' => $sheetName, 'unit' => $unit, 'rows' => 0, 'added' => 0, 'updated' => 0, 'failed' => 0];

            foreach ($rows as $row) {
                $nis = $this->nullable($row[$cols['nis']] ?? null);
                if (empty($nis)) {
                    continue; // NIS wajib ada
                }

                $report['total_rows']++;
                $sheetStats['rows']++;
                $report['diproses']++;

                $data = [
                    'nis' => (string) $nis,
                    'nis2' => $this->nullable($row[$cols['nis2']] ?? null),
                    'nama' => (string) ($this->bersihkan($row[$cols['nama']] ?? null) ?? ''),
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

                if (empty($data['nama'])) {
                    $report['error']++;
                    $sheetStats['failed']++;
                    continue;
                }

                $exists = Santri::where('nis', $data['nis'])->exists();
                $santri = Santri::updateOrCreate(['nis' => $data['nis']], $data);
                $santri->syncWaliAccount();

                if ($exists) {
                    $report['diupdate']++;
                    $sheetStats['updated']++;
                } else {
                    $report['ditambah']++;
                    $sheetStats['added']++;
                }

                if ($fotoFolder && $this->pasangFoto($data['nis'], $fotoFolder)) {
                    $report['foto_terpasang']++;
                }
            }

            $report['sheets'][] = $sheetStats;
        }

        $spreadsheet->disconnectWorksheets();

        return $report;
    }

    /** Unit dari nama sheet: SANTRI BARU -> BARU, sisanya nama sheet asli. */
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
            $key = strtolower((string) $this->bersihkan($h));
            if (empty($key)) continue;

            foreach ($map as $field => $patterns) {
                if ($index[$field] !== null) continue; // Sudah terpetakan
                
                foreach ($patterns as $pattern) {
                    if ($key === $pattern || str_contains($key, $pattern)) {
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
        if ($index['alamat'] === null && isset($header[2])) {
            $index['alamat'] = 2;
        }
        if ($index['tanggal_lahir'] === null && isset($header[3])) {
            $index['tanggal_lahir'] = 3;
        }
        if ($index['jenis_kelamin'] === null && isset($header[4])) {
            $index['jenis_kelamin'] = 4;
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
                // Fallback jika bukan excel timestamp
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
        $formats = ['d-m-Y', 'd/m/Y', 'd M Y', 'd F Y', 'Y-m-d', 'Y/m/d'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $valueStr)->format('Y-m-d');
            } catch (\Exception $e) {
                // Lanjut
            }
        }
        
        return null;
    }

    /** Normalisasi '0.0'/kosong/strip -> null. */
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
        $files = glob(rtrim($folder, "/\\") . "/{$nis}.*");
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
