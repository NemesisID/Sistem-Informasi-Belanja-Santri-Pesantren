<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Santri extends Model
{
    /** @use HasFactory<\Database\Factories\SantriFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date:Y-m-d',
            'saldo' => 'integer',
        ];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function walis(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'santri_user');
    }

    protected static function booted(): void
    {
        static::deleting(function (Santri $santri) {
            // Ambil semua akun wali yang terhubung dengan santri ini
            $walis = $santri->walis()->get();

            // Cari juga akun wali yang memiliki username sama dengan NIS santri ini
            if ($santri->nis) {
                $waliByNis = User::where('username', (string) $santri->nis)->where('role', 'wali')->get();
                $walis = $walis->merge($waliByNis)->unique('id');
            }

            foreach ($walis as $wali) {
                // Lepaskan relasi pivot santri ini
                $wali->santris()->detach($santri->id);

                // Hapus akun wali jika akun dibuat khusus santri ini (username == NIS) atau sudah tidak punya santri lain
                if ($wali->username === (string) $santri->nis || $wali->santris()->count() === 0) {
                    $wali->delete();
                }
            }
        });
    }

    /**
     * Sinkronkan akun wali: username = NIS, password = ddmmyyyy(tanggal lahir) atau NIS jika tgl lahir kosong.
     */
    public function syncWaliAccount(): ?User
    {
        if (! $this->nis) {
            return null;
        }

        $wali = $this->walis()->first()
            ?? User::where('username', (string) $this->nis)->first();

        // Jangan timpa akun jika username bentrok dengan admin/staff
        if ($wali && $wali->role !== 'wali') {
            return null;
        }

        $data = [
            'name'      => $this->nama,
            'username'  => (string) $this->nis,
            'role'      => 'wali',
            'is_active' => true,
        ];

        if ($wali) {
            $wali->update($data);
        } else {
            $data['password'] = $this->tanggal_lahir 
                ? $this->tanggal_lahir->format('dmY') 
                : (string) $this->nis;
            $wali = User::create($data);
        }

        $this->walis()->syncWithoutDetaching([$wali->id]);

        return $wali;
    }

    /** URL foto untuk verifikasi penarikan; null bila belum ada foto. */
    public function getFotoUrlAttribute(): ?string
    {
        return $this->foto_slug ? url('/foto/' . $this->foto_slug) : null;
    }
}
