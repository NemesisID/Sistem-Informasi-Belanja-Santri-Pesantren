<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Administrator',
            'username' => 'admin',
            'email' => null,
            'role' => 'admin',
        ]);

        User::factory()->staff()->create([
            'name' => 'Staff Rumah Koin',
            'username' => 'staff',
            'email' => null,
            'nip' => '2025001',
            'jabatan' => 'Kasir',
            'shift' => 'Pagi',
        ]);
    }
}
