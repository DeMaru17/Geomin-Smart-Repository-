<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    // Relasi ke tabel Document (1 Kategori punya Banyak Dokumen)
    public function documents()
    {
        return $this->hasMany(Document::class, 'category_id');
    }
}
