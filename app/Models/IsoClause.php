<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IsoClause extends Model
{
    use HasFactory;

    protected $fillable = [
        'clause_number',
        'description',
    ];

    // Relasi balik Many-to-Many ke tabel Document
    public function documents()
    {
        return $this->belongsToMany(Document::class, 'document_iso_clauses');
    }
}
