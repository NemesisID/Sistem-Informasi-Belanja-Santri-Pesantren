<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffRequest extends FormRequest
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
        $id = $this->route('user')?->id ?? null;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['nullable', 'string', Rule::unique('users', 'username')->ignore($id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'nip' => ['nullable', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'shift' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // Password wajib saat buat, opsional saat edit (kosong = tidak diganti)
        if ($this->isMethod('post')) {
            $rules['password'] = ['required', 'string', 'min:6'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:6'];
        }

        return $rules;
    }
}
