<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SantriRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = $this->route('santri')?->id ?? null;

        return [
            'nis' => ['sometimes', 'required', 'string', Rule::unique('santris', 'nis')->ignore($id)],
            'nis2' => ['nullable', 'string'],
            'nama' => ['sometimes', 'required', 'string', 'max:255'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'jenis_kelamin' => ['sometimes', 'required', Rule::in(['L', 'P'])],
            'alamat' => ['nullable', 'string'],
            'kelas' => ['nullable', 'string', 'max:255'],
            'kelas_detail' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', Rule::in(['MTS', 'MA', 'SMP', 'SMA', 'SMK', 'BARU'])],
            'va_jajan' => ['nullable', 'string', Rule::unique('santris', 'va_jajan')->ignore($id)],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif'])],
            'foto' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];
    }
}
