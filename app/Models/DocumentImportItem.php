<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentImportItem extends Model
{
    use HasFactory;

    protected $table = 'document_import_items';

    protected $fillable = [
        'batch_id',
        'source_filename',
        'source_relative_path',
        'file_extension',
        'file_size_bytes',
        'file_hash',
        'parsed_document_number',
        'parsed_revision_number',
        'role_hint',
        'matched_document_id',
        'matched_revision_id',
        'target_slot',
        'slot_current_status',
        'match_status',
        'decision',
        'is_selected',
        'install_status',
        'installed_path',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'is_selected' => 'boolean',
        ];
    }

    /**
     * Relasi ke batch pemindaian induk.
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(DocumentImportBatch::class, 'batch_id');
    }

    /**
     * Relasi ke dokumen target yang dicocokkan (jika ada).
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'matched_document_id');
    }

    /**
     * Relasi ke revisi target yang dicocokkan (jika ada).
     */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(DocumentRevision::class, 'matched_revision_id');
    }

    /**
     * Scope untuk item yang memenuhi syarat untuk dipilih/dipasang.
     */
    public function scopeEligible($query)
    {
        return $query->whereIn('decision', ['READY_TO_ATTACH', 'PLACEHOLDER_REPLACEABLE', 'MANUAL_RESOLVED']);
    }

    /**
     * Scope untuk item yang dipilih oleh admin.
     */
    public function scopeSelected($query)
    {
        return $query->where('is_selected', true);
    }

    /**
     * Scope untuk item yang membutuhkan peninjauan manual admin.
     */
    public function scopeManualReview($query)
    {
        return $query->whereIn('decision', ['AMBIGUOUS_MATCH', 'CONFLICT_DUPLICATE_SOURCE', 'MANUAL_REVIEW']);
    }

    /**
     * Cek apakah item ini aman untuk dicentang/dipasang.
     */
    public function isEligibleForInstallation(): bool
    {
        return in_array($this->decision, ['READY_TO_ATTACH', 'PLACEHOLDER_REPLACEABLE', 'MANUAL_RESOLVED'])
            && ! in_array($this->slot_current_status, ['SLOT_OCCUPIED_LOCKED', 'SLOT_STORAGE_UNREACHABLE']);
    }
}
