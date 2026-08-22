<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function headings(): array
    {
        return [
            "No",
            "Tanggal & Waktu",
            "NIS",
            "Nama Santri",
            "Jenis",
            "Kategori Transaksi",
            "Nominal (Rp)",
            "Saldo Setelah (Rp)",
            "Keterangan",
            "Petugas / Staff",
        ];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $rows = $this->query->with("santri:id,nis,nama", "creator:id,name")->get();

        return $rows->values()->map(function ($t, $i) {
            $isMasuk = $t->nominal > 0 || $t->tipe === "topup";
            
            $kategori = match ($t->tipe) {
                "topup", "bni" => "Setor VA BNI",
                "tarik_koin", "penarikan" => "Penarikan Koin",
                "penyesuaian" => $t->nominal >= 0 ? "Penyesuaian Tambah" : "Penyesuaian Kurang",
                default => ucfirst(str_replace("_", " ", (string) $t->tipe)),
            };

            return [
                "No" => $i + 1,
                "Tanggal & Waktu" => $t->created_at?->format("Y-m-d H:i:s"),
                "NIS" => $t->santri?->nis ?? "-",
                "Nama Santri" => $t->santri?->nama ?? "-",
                "Jenis" => $isMasuk ? "Masuk" : "Keluar",
                "Kategori Transaksi" => $kategori,
                "Nominal (Rp)" => (int) abs($t->nominal),
                "Saldo Setelah (Rp)" => (int) $t->saldo_setelah,
                "Keterangan" => $t->keterangan ?? "-",
                "Petugas / Staff" => $t->creator?->name ?? "Administrator",
            ];
        });
    }

    public function styles(Worksheet $sheet): ?array
    {
        return [
            1 => [
                "font" => ["bold" => true, "color" => ["rgb" => "FFFFFF"]],
                "fill" => [
                    "fillType" => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    "startColor" => ["rgb" => "1E5E3A"],
                ],
            ],
        ];
    }
}