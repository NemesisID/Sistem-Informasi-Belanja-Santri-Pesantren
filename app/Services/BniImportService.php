<?php

namespace App\Services;

use App\Models\BniUploadItem;
use App\Models\Santri;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Parse mutasi BNI e-Collection (.xlsx/.xls/.csv) sesuai format paydata BNI:
 * kolom Account Number, Prefix, Client Name, VA Number, Customer Name,
 * Payment Date, Journal Number, Payment Amount, Billing ID, Currency.
 *
 * Aturan:
 * - Hanya baris dengan Billing ID diawali "JJN" (uang jajan santri); PEM* diabaikan.
 * - VA dicocokkan ke santri via va_jajan/va_pembayaran (VA penuh ATAU nomor customer
 *   hasil ekstrak = VA minus Prefix minus Client).
 * - Anti double-credit lintas file: item dengan journal (atau VA+nominal+tanggal) yang
 *   sudah pernah masuk ditandai invalid "Duplikat".
 */
class BniImportService
{
    /**
     * Baca file → data item (belum disimpan).
     *
     * @return array{items: array, valid: int, invalid: int}
     */
    public function parse(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray();
        $header = array_shift($rows) ?? [];
        $cols = $this->petaKolom($header);

        $items = [];
        $seen = []; // dedup intra-file

        foreach ($rows as $row) {
            $billing = $this->bersihkan($row[$cols['billing']] ?? null);

            // Hanya transaksi uang jajan: Billing ID diawali JJN
            if ($billing === null || ! str_starts_with(strtoupper($billing), 'JJN')) {
                continue;
            }

            $va = $this->bersihkan($row[$cols['va']] ?? null);
            $nominal = $this->nominal($row[$cols['nominal']] ?? null);

            if ($va === null || $nominal === null || $nominal <= 0) {
                continue; // baris kosong / tak bernilai
            }

            $tanggal = $this->tanggal($row[$cols['tanggal']] ?? null);
            $journal = $this->bersihkan($row[$cols['journal']] ?? null);
            $customer = $this->customerDariVa(
                $va,
                $this->bersihkan($row[$cols['prefix']] ?? null),
                $this->bersihkan($row[$cols['client']] ?? null),
            );

            $dedupKey = $journal ? "J:{$journal}" : "V:{$va}|{$nominal}|{$tanggal}";
            $duplicate = $this->cariDuplikat($dedupKey, $seen);

            $item = [
                'va' => $va,
                'nama' => $this->bersihkan($row[$cols['nama']] ?? null),
                'nominal' => $nominal,
                'tanggal' => $tanggal,
                'journal' => $journal,
                'billing_id' => $billing,
                'dedup_key' => $dedupKey,
                'santri_id' => null,
                'status_valid' => false,
                'catatan' => $duplicate
                    ? "Duplikat: transaksi sama sudah ada (upload #{$duplicate['upload_id']})"
                    : "VA {$va} tidak cocok dengan santri mana pun",
            ];

            if (! $duplicate) {
                $santri = $this->cariSantri($va, $customer);
                $item['santri_id'] = $santri?->id;
                $item['status_valid'] = $santri !== null;
                $item['catatan'] = $santri ? null : $item['catatan'];
            }

            $seen[$dedupKey] = $item['catatan'];
            $items[] = $item;
        }

        $spreadsheet->disconnectWorksheets();

        $valid = count(array_filter($items, fn ($i) => $i['status_valid']));

        return [
            'items' => $items,
            'valid' => $valid,
            'invalid' => count($items) - $valid,
        ];
    }

    /** Cari duplikat: sudah ada di DB (yang valid/sudah diterapkan) atau di file yang sama ($seen). */
    private function cariDuplikat(string $dedupKey, array $seen): ?array
    {
        if (isset($seen[$dedupKey])) {
            return ['upload_id' => 'file ini', 'catatan' => $seen[$dedupKey]];
        }

        // Hanya anggap duplikat jika item sebelumnya valid (masih antri) atau sudah diterapkan
        $existing = BniUploadItem::where('dedup_key', $dedupKey)
            ->where(function ($q) {
                $q->where('status_valid', true)->orWhere('diterapkan', true);
            })
            ->first();

        return $existing ? ['upload_id' => $existing->upload_id, 'catatan' => $existing->catatan] : null;
    }

    /** Cocokkan VA file ke santri: VA penuh atau nomor customer (VA - prefix - client). */
    private function cariSantri(string $va, ?string $customer): ?Santri
    {
        return Santri::query()
            ->where(function ($q) use ($va, $customer) {
                $q->where('va_jajan', $va);
                if ($customer !== null && $customer !== '') {
                    $q->orWhere('va_jajan', $customer);
                }
            })
            ->first();
    }

    /** Nomor customer = VA minus Prefix minus Client (mis. 988 + 44333 + 52026355). */
    private function customerDariVa(string $va, ?string $prefix, ?string $client): ?string
    {
        if ($prefix === null || $client === null) {
            return null;
        }

        $awal = $prefix.$client;
        if (str_starts_with($va, $awal)) {
            return substr($va, strlen($awal)) ?: null;
        }

        return null;
    }

    private function petaKolom(array $header): array
    {
        $index = [
            'va' => null, 'nama' => null, 'nominal' => null, 'tanggal' => null,
            'journal' => null, 'billing' => null, 'prefix' => null, 'client' => null,
        ];

        foreach ($header as $i => $h) {
            $key = strtolower((string) $this->bersihkan($h));

            if (preg_match('/\bva\b|nomor va|no\.?\s*va/i', $key)) {
                $index['va'] ??= $i;
            }
            if (preg_match('/nama|name/i', $key) && ! preg_match('/client|layanan|billing/i', $key)) {
                $index['nama'] ??= $i;
            }
            if (preg_match('/nominal|jumlah|amount|total|nilai/i', $key)) {
                $index['nominal'] ??= $i;
            }
            if (preg_match('/^tanggal|tgl|date/i', $key)) {
                $index['tanggal'] ??= $i;
            }
            if (preg_match('/journal|referensi|no\.?\s*(trx|transaksi)|trx id/i', $key)) {
                $index['journal'] ??= $i;
            }
            if (preg_match('/billing/i', $key)) {
                $index['billing'] ??= $i;
            }
            if (preg_match('/^prefix/i', $key)) {
                $index['prefix'] ??= $i;
            }
            if (preg_match('/client/i', $key)) {
                $index['client'] ??= $i;
            }
        }

        return $index;
    }

    private function nominal(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = (string) str_replace([',', '.'], '', (string) $value);
        if (! is_numeric($clean)) {
            return null;
        }
        return (int) round((float) $clean);
    }

    private function tanggal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d H:i:s');
        }
        $parsed = strtotime((string) $value);
        return $parsed ? date('Y-m-d H:i:s', $parsed) : null;
    }

    private function bersihkan(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }
}
