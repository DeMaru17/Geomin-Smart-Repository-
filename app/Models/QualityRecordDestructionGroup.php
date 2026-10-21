<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class QualityRecordDestructionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'bapr_number',
        'destruction_date',
        'method',
        'destroyed_by_id',
        'acknowledged_by_id',
        'status',
        'finalized_at',
        'snapshot_data',
    ];

    protected $casts = [
        'destruction_date' => 'date',
        'finalized_at' => 'datetime',
        'snapshot_data' => 'array',
    ];

    /**
     * Relasi ke seluruh rekaman mutu yang dimusnahkan dalam grup BAPR ini.
     */
    public function records(): HasMany
    {
        return $this->hasMany(QualityRecord::class, 'quality_record_destruction_group_id');
    }

    /**
     * Relasi ke User yang memusnahkan (Pengendali Rekaman / Admin).
     */
    public function destroyedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'destroyed_by_id');
    }

    /**
     * Relasi ke User yang mengetahui (Penanggung Jawab Mutu / Lab Manager).
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_id');
    }

    /**
     * Cari grup berstatus 'open' untuk tanggal pemusnahan tertentu, atau buat baru jika belum ada.
     */
    public static function findOrCreateOpenGroupForDate(
        string|Carbon $destructionDate,
        ?string $method = null,
        ?int $destroyedById = null,
        ?int $acknowledgedById = null
    ): static {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $carbonDate = $destructionDate instanceof Carbon
            ? $destructionDate->copy()->setTimezone($timezone)
            : Carbon::parse($destructionDate, $timezone);

        $dateString = $carbonDate->toDateString();

        return DB::transaction(function () use ($dateString, $carbonDate, $method, $destroyedById, $acknowledgedById) {
            $group = static::query()
                ->lockForUpdate()
                ->where('destruction_date', $dateString)
                ->where('status', 'open')
                ->first();

            if (! $group) {
                $baprNumber = static::generateBaprNumber($carbonDate);
                $group = static::create([
                    'bapr_number' => $baprNumber,
                    'destruction_date' => $dateString,
                    'method' => $method ?? 'Dihapus dari server & backup',
                    'destroyed_by_id' => $destroyedById,
                    'acknowledged_by_id' => $acknowledgedById,
                    'status' => 'open',
                ]);
            } else {
                $updates = [];
                if ($method && empty($group->method)) {
                    $updates['method'] = $method;
                }
                if ($destroyedById && ! $group->destroyed_by_id) {
                    $updates['destroyed_by_id'] = $destroyedById;
                }
                if ($acknowledgedById && ! $group->acknowledged_by_id) {
                    $updates['acknowledged_by_id'] = $acknowledgedById;
                }
                if (! empty($updates)) {
                    $group->update($updates);
                }
            }

            return $group;
        });
    }

    /**
     * Generate nomor BAPR otomatis dengan format XXX/LAB-BAPR/DD/MM/YYYY.
     * Menggunakan row locking untuk mencegah race condition.
     */
    public static function generateBaprNumber(?Carbon $date = null): string
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $date = $date ? $date->copy()->setTimezone($timezone) : now()->setTimezone($timezone);
        $year = $date->year;

        $count = DB::transaction(function () use ($year) {
            return static::query()
                ->lockForUpdate()
                ->whereYear('created_at', $year)
                ->count();
        });

        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $day = $date->format('d');
        $month = $date->format('m');

        return "{$sequence}/LAB-BAPR/{$day}/{$month}/{$year}";
    }

    /**
     * Mengecek apakah grup BAPR ini sudah difinalisasi.
     */
    public function isFinalized(): bool
    {
        return $this->status === 'finalized' && $this->finalized_at !== null;
    }

    /**
     * Finalisasi grup BAPR dan bekukan snapshot historis seluruh rekaman yang termasuk di dalamnya.
     */
    public function finalize(): void
    {
        if ($this->isFinalized()) {
            return;
        }

        $this->loadMissing(['records.document', 'destroyedBy', 'acknowledgedBy']);

        $snapshots = [];
        foreach ($this->records as $record) {
            $doc = $record->document;
            $retentionMonths = $doc?->retention_period_months ?? QualityRecord::DEFAULT_RETENTION_MONTHS;

            $snapshots[] = [
                'quality_record_id' => $record->id,
                'record_name' => $record->record_name,
                'document_number' => $doc?->document_number ?? '',
                'document_title' => $doc?->title ?? '',
                'record_date' => $record->record_date?->format('d/m/Y') ?? '',
                'archived_date' => $record->created_at?->format('d/m/Y') ?? '',
                'retention_period' => "{$retentionMonths} Bulan",
                'destruction_date' => ($record->destruction_date ?? $this->destruction_date)?->format('d/m/Y') ?? '',
                'method' => $record->destruction_method ?? $this->method ?? 'Dihapus dari server & backup',
                'destroyed_by_name' => $this->destroyedBy?->name ?? '',
                'acknowledged_by_name' => $this->acknowledgedBy?->name ?? '',
            ];
        }

        $this->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'snapshot_data' => $snapshots,
        ]);
    }
}
