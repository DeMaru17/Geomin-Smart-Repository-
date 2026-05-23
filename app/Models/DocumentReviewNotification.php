<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReviewNotification extends Model
{
    use HasFactory;


    protected $fillable = [
        'document_id',
        'due_date',
        'panel_notified_at',
        'email_notified_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'due_date' => 'date',
        'panel_notified_at' => 'datetime',
        'email_notified_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    // Relasi ke tabel Document (1 Notification tracking punya 1 Dokumen)
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
