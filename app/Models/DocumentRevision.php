<?php

namespace App\Models;

use App\Jobs\ExtractPdfTextJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'revision_number',
        'file_path',
        'status',
        'change_summary',
        'qr_token',
        'uploader_id',
        'word_file_path',
        'extracted_text',
    ];

    // Relasi balik ke tabel Document induknya
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    protected static function booted()
    {
        static::saved(function ($revision) {
            if (in_array($revision->status, ['Published', 'Terbit'])) {
                static::query()
                    ->where('document_id', $revision->document_id)
                    ->where('id', '!=', $revision->id)
                    ->whereIn('status', ['Published', 'Terbit'])
                    ->update(['status' => 'Obsolete']);
            }
        });

        static::created(function ($revision) {
            if ($revision->file_path && str_ends_with(strtolower($revision->file_path), '.pdf')) {
                ExtractPdfTextJob::dispatch($revision->id);
            }
        });

        static::updated(function ($revision) {
            if ($revision->wasChanged('file_path') && $revision->file_path && str_ends_with(strtolower($revision->file_path), '.pdf')) {
                ExtractPdfTextJob::dispatch($revision->id);
            }
        });
    }
}
