<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    // Relasi ke tabel Document (1 Departemen punya Banyak Dokumen)
    public function documents()
    {
        return $this->hasMany(Document::class, 'department_id');
    }
}
