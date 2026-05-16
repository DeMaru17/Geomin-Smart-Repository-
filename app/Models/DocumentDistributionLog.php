<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDistributionLog extends Model
{
    protected $fillable = [
        'document_revision_id',
        'user_id',
        'recipient_name',
        'purpose',
        'accessed_at',
        'is_qr_access',
        'action',
    ];

    protected $casts = [
        'accessed_at' => 'datetime',
        'is_qr_access' => 'boolean',
    ];

    /**
     * Relasi ke DocumentRevision (log ini milik satu revisi dokumen)
     */
    public function documentRevision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class);
    }

    /**
     * Relasi ke User pada koneksi mysql_hris
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
