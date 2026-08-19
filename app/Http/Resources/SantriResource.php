<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SantriResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nis' => $this->nis,
            'nis2' => $this->nis2,
            'nama' => $this->nama,
            'tempat_lahir' => $this->tempat_lahir,
            'tanggal_lahir' => $this->tanggal_lahir?->format('Y-m-d'),
            'jenis_kelamin' => $this->jenis_kelamin,
            'alamat' => $this->alamat,
            'kelas' => $this->kelas,
            'kelas_detail' => $this->kelas_detail,
            'tags' => $this->tags,
            'note' => $this->note,
            'unit' => $this->unit,
            'va_jajan' => $this->va_jajan,
            'status' => $this->status,
            'saldo' => (int) $this->saldo,
            'foto_url' => $this->foto_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
