<?php

namespace Tests\Feature;

use App\Models\Santri;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SantriFotoTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_santri_dengan_foto_multipart(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $santri = Santri::factory()->create(['nis' => '12345678']);

        $response = $this->actingAs($admin)->put('/api/santris/'.$santri->id, [
            'nama' => 'Ahmad Fauzi',
            'nis' => '12345678',
            'jenis_kelamin' => 'L',
            'unit' => 'MTS',
            'foto' => UploadedFile::fake()->image('12345678.jpg'),
        ]);

        $response->assertOk();
        $this->assertNotNull($santri->fresh()->foto_path);
        $this->assertNotNull($response->json('data.foto_url'));
        Storage::disk('local')->assertExists($santri->fresh()->foto_path);
    }
}
