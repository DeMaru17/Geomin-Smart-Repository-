<?php

namespace App\Models;

use App\Jobs\ExtractPdfTextJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        static::creating(function ($revision) {
            if (empty($revision->qr_token)) {
                $attempts = 0;
                do {
                    $token = Str::random(16);
                    $exists = static::where('qr_token', $token)->exists();
                    $attempts++;
                } while ($exists && $attempts < 3);

                if ($exists) {
                    throw new \RuntimeException('Failed to generate unique QR token after 3 attempts.');
                }

                $revision->qr_token = $token;
            }
        });

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
