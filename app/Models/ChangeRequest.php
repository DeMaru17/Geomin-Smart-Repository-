<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangeRequest extends Model
{
    use HasFactory;

    // protected $connection = 'mysql_hris';

    protected $fillable = [
        'document_id',
        'proposer_id',
        'proposal_date',
        'reason',
        'proposed_changes',
        'approval_status',
        'approval_notes',
        'effective_date',
        'approver_id',
    ];

    protected $casts = [
        'proposed_changes' => 'array',
        'proposal_date' => 'date',
        'effective_date' => 'date',
    ];

    // Relasi ke tabel Document (1 Change Request punya 1 Dokumen)
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Relasi ke tabel User sebagai pengusul (cross-database)
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    // Relasi ke tabel User sebagai penyetuju (cross-database)
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
