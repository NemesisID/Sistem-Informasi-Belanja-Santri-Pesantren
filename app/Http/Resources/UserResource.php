<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            'role' => $this->role,
            'phone' => $this->phone,
            'is_active' => (bool) $this->is_active,
            'nip' => $this->nip,
            'jabatan' => $this->jabatan,
            'shift' => $this->shift,
            'created_at' => $this->created_at?->toIso8601String(),
            'santris' => $this->whenLoaded('santris', fn () => $this->santris->map(fn ($s) => [
                'id' => $s->id,
                'nis' => $s->nis,
                'nama' => $s->nama,
                'va_jajan' => $s->va_jajan,
            ])),
        ];
    }
}
