<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    // Ledger append-only — tidak boleh di-update/di-hapus lewat mass assignment biasa.
    public const CREATED_AT = 'created_at';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'saldo_sebelum' => 'integer',
            'saldo_setelah' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }

    public function bniUploadItem(): BelongsTo
    {
        return $this->belongsTo(BniUploadItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
