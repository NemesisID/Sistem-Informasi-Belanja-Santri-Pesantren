<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TransactionsExport implements FromCollection, WithHeadings
{
    public function __construct(private readonly Builder $query)
    {
    }

    public function headings(): array
    {
        return ['No', 'Tanggal', 'NIS', 'Nama Santri', 'Tipe', 'Nominal (Rp)', 'Saldo Setelah', 'Keterangan', 'Oleh'];
    }

    public function collection(): \Illuminate\Support\Collection
    {
        $rows = $this->query->with('santri:id,nis,nama', 'creator:id,name')->get();

        return $rows->values()->map(fn ($t, $i) => [
            'No' => $i + 1,
            'Tanggal' => $t->created_at?->format('Y-m-d H:i'),
            'NIS' => $t->santri?->nis,
            'Nama Santri' => $t->santri?->nama,
            'Tipe' => $t->tipe,
            'Nominal (Rp)' => (int) $t->nominal,
            'Saldo Setelah' => (int) $t->saldo_setelah,
            'Keterangan' => $t->keterangan,
            'Oleh' => $t->creator?->name,
        ]);
    }
}
