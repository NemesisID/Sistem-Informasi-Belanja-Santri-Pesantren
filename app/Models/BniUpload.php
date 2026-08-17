<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BniUpload extends Model
{
    /** @use HasFactory<\Database\Factories\BniUploadFactory> */
    use HasFactory;

    public const UPDATED_AT = null;
    public const CREATED_AT = 'created_at';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'jumlah_total' => 'integer',
            'jumlah_valid' => 'integer',
            'jumlah_invalid' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(BniUploadItem::class, 'upload_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
