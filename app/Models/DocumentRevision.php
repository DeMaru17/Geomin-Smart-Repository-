<?php

namespace App\Models;

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
}
