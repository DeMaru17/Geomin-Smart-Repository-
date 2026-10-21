<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DocumentDestruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bapd_number',
        'document_destruction_group_id',
        'document_id',
        'proposer_id',
        'reason',
        'method',
        'status',
        'approver_id',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    // Relasi ke tabel Document (1 Pemusnahan punya 1 Dokumen)
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Relasi ke grup BAPD (BAPD export group)
    public function group(): BelongsTo
    {
        return $this->belongsTo(DocumentDestructionGroup::class, 'document_destruction_group_id');
    }

    // Relasi ke tabel User sebagai pengusul (cross-connection mysql_hris)
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    // Relasi ke tabel User sebagai penyetuju (cross-connection mysql_hris)
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    protected static function booted()
    {
        static::creating(function ($destruction) {
            if (empty($destruction->bapd_number)) {
                $destruction->bapd_number = static::generateBapdNumber();
            }
        });

        static::saved(function ($destruction) {
            if ($destruction->status === 'Disetujui' && $destruction->approved_at && ! $destruction->document_destruction_group_id) {
                $destruction->assignToApprovalGroup();
            }
        });
    }

    /**
     * Daftarkan pengajuan pemusnahan yang disetujui ke dalam grup BAPD sesuai tanggal persetujuan.
     */
    public function assignToApprovalGroup(): ?DocumentDestructionGroup
    {
        if ($this->status !== 'Disetujui' || ! $this->approved_at) {
            return null;
        }

        if ($this->document_destruction_group_id && $this->group) {
            return $this->group;
        }

        $group = DocumentDestructionGroup::findOrCreateOpenGroupForDate($this->approved_at);
        $this->document_destruction_group_id = $group->id;
        $this->saveQuietly();

        return $group;
    }

    /**
     * Generate nomor BAPD otomatis dengan format XXX/LAB-BAPD/DD/MM/YYYY.
     * Menggunakan row locking untuk mencegah race condition.
     */
    protected static function generateBapdNumber(): string
    {
        $now = now();
        $year = $now->year;

        $count = DB::transaction(function () use ($year) {
            return static::query()
                ->lockForUpdate()
                ->whereYear('created_at', $year)
                ->count();
        });

        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $day = $now->format('d');
        $month = $now->format('m');

        return "{$sequence}/LAB-BAPD/{$day}/{$month}/{$year}";
    }
}
