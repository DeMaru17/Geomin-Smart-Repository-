<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'review_date',
        'reviewer_id',
        'evaluation_status',
        'remarks',
    ];

    protected $casts = [
        'review_date' => 'date',
    ];

    // Relasi ke tabel Document (1 Review punya 1 Dokumen)
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Relasi ke tabel User sebagai reviewer (cross-database, mysql_hris)
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
