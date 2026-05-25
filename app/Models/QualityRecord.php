<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QualityRecord extends Model
{
    use HasFactory;

    const DEFAULT_RETENTION_MONTHS = 36;

    protected $fillable = [
        'document_id',
        'record_name',
        'record_date',
        'confidentiality',
        'description',
        'physical_location',
        'file_path',
        'expiration_date',
        'status',
        'uploader_id',
    ];

    protected $casts = [
        'record_date' => 'date',
        'expiration_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (QualityRecord $record) {
            // Auto-set uploader_id from authenticated user
            $record->uploader_id = auth()->id();

            // Calculate expiration_date
            $record->expiration_date = $record->calculateExpirationDate();
        });

        static::updating(function (QualityRecord $record) {
            // Recalculate expiration_date if record_date or document_id changed
            if ($record->isDirty('record_date') || $record->isDirty('document_id')) {
                $record->expiration_date = $record->calculateExpirationDate();
            }
        });
    }

    // Relasi ke tabel Document (1 Rekaman Mutu punya 1 Dokumen Master)
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    // Relasi ke tabel User sebagai uploader (cross-connection mysql_hris)
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * Hitung tanggal kedaluwarsa berdasarkan record_date + retention_period_months.
     * Default ke 36 bulan jika retention_period_months null, 0, atau negatif.
     */
    public function calculateExpirationDate(): Carbon
    {
        $retentionMonths = $this->document?->retention_period_months;

        if (is_null($retentionMonths) || $retentionMonths <= 0) {
            $retentionMonths = self::DEFAULT_RETENTION_MONTHS;
        }

        $recordDate = $this->record_date instanceof Carbon
            ? $this->record_date
            : Carbon::parse($this->record_date);

        return $recordDate->copy()->addMonths($retentionMonths);
    }

    /**
     * Cek apakah rekaman sudah kedaluwarsa.
     */
    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    /**
     * Cek apakah rekaman mendekati kedaluwarsa dalam jumlah hari tertentu.
     */
    public function isNearExpiration(int $days = 30): bool
    {
        if (! $this->expiration_date) {
            return false;
        }

        $now = Carbon::now()->startOfDay();
        $expirationDate = $this->expiration_date->startOfDay();

        return $expirationDate->greaterThanOrEqualTo($now)
            && $expirationDate->lessThanOrEqualTo($now->copy()->addDays($days));
    }
}
