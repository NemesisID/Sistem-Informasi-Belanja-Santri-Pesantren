<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BniUploadItem extends Model
{
    /** @use HasFactory<\Database\Factories\BniUploadItemFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'tanggal' => 'date:Y-m-d',
            'status_valid' => 'boolean',
            'diterapkan' => 'boolean',
            'applied_at' => 'datetime',
        ];
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(BniUpload::class, 'upload_id');
    }

    public function santri(): BelongsTo
    {
        return $this->belongsTo(Santri::class);
    }
}
