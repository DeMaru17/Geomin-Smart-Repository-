<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentImportBatch extends Model
{
    use HasFactory;

    protected $table = 'document_import_batches';

    protected $fillable = [
        'batch_number',
        'scanned_by',
        'source_directory',
        'status',
        'total_files_scanned',
        'total_matched',
        'total_installed',
        'total_skipped',
        'total_failed',
        'confirmed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_files_scanned' => 'integer',
            'total_matched' => 'integer',
            'total_installed' => 'integer',
            'total_skipped' => 'integer',
            'total_failed' => 'integer',
            'confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * Relasi ke seluruh item berkas dalam batch pemindaian ini.
     */
    public function items(): HasMany
    {
        return $this->hasMany(DocumentImportItem::class, 'batch_id');
    }

    /**
     * Relasi ke user admin yang menjalankan pemindaian (pada database HRIS).
     */
    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    /**
     * Scope untuk batch yang masih dalam proses atau siap preview.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['Scanning', 'Preview_Ready', 'Processing']);
    }
}
