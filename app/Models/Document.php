<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_number',
        'title',
        'category_id',
        'department_id',
        'is_external',
        'publication_year',
        'remarks',
        'retention_period_months',
    ];

    // Relasi ke tabel Department (1 Dokumen punya 1 Departemen)
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    // Relasi ke tabel DocumentCategory (1 Dokumen punya 1 Kategori)
    public function category()
    {
        return $this->belongsTo(DocumentCategory::class);
    }

    // Relasi Many-to-Many ke tabel IsoClause melalui tabel pivot
    public function isoClauses()
    {
        return $this->belongsToMany(IsoClause::class, 'document_iso_clauses');
    }

    // Relasi ke tabel DocumentRevision (1 Dokumen punya Banyak Revisi)
    public function revisions()
    {
        return $this->hasMany(DocumentRevision::class);
    }

    public function revisions_latest()
    {
        return $this->hasOne(DocumentRevision::class)->latestOfMany();
    }
}
