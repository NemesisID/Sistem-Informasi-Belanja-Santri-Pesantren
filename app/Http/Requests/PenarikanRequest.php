<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PenarikanRequest extends FormRequest
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
            'santri_id' => ['required', 'integer', 'exists:santris,id'],
            'nominal' => ['required', 'integer', 'min:'.config('koin.min_penarikan')],
        ];
    }
}
