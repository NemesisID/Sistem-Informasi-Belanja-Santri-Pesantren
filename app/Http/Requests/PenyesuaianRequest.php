<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PenyesuaianRequest extends FormRequest
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
        return [
            'aksi' => ['required', Rule::in(['tambah', 'kurangi'])],
            'nominal' => ['required', 'integer', 'min:1'],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }
}
