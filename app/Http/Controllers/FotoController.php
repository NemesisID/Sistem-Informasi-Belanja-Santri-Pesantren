<?php

namespace App\Http\Controllers;

use App\Models\Santri;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FotoController extends Controller
{
    public function show(string $slug): StreamedResponse
    {
        $santri = Santri::where('foto_slug', $slug)->firstOrFail();

        return Storage::disk('local')->response($santri->foto_path);
    }
}
