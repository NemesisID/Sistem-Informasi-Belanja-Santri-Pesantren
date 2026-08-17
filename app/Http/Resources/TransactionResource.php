<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipe' => $this->tipe,
            'nominal' => (int) $this->nominal,
            'saldo_sebelum' => (int) $this->saldo_sebelum,
            'saldo_setelah' => (int) $this->saldo_setelah,
            'keterangan' => $this->keterangan,
            'created_at' => $this->created_at?->toIso8601String(),
            'santri' => $this->whenLoaded('santri', fn () => [
                'id' => $this->santri->id,
                'nis' => $this->santri->nis,
                'nama' => $this->santri->nama,
            ]),
            'created_by' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
        ];
    }
}
