<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DocumentDestructionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'bapd_number',
        'approval_date',
        'status',
        'finalized_at',
        'snapshot_data',
    ];

    protected $casts = [
        'approval_date' => 'date',
        'finalized_at' => 'datetime',
        'snapshot_data' => 'array',
    ];

    /**
     * Relasi ke seluruh pengajuan pemusnahan dokumen yang tergabung dalam grup BAPD ini.
     */
    public function destructions(): HasMany
    {
        return $this->hasMany(DocumentDestruction::class, 'document_destruction_group_id');
    }

    /**
     * Cari grup berstatus 'open' untuk tanggal persetujuan tertentu, atau buat baru jika belum ada.
     */
    public static function findOrCreateOpenGroupForDate(string|Carbon $approvalDate): static
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $carbonDate = $approvalDate instanceof Carbon
            ? $approvalDate->copy()->setTimezone($timezone)
            : Carbon::parse($approvalDate, $timezone);

        $dateString = $carbonDate->toDateString();

        return DB::transaction(function () use ($dateString, $carbonDate) {
            $group = static::query()
                ->lockForUpdate()
                ->where('approval_date', $dateString)
                ->where('status', 'open')
                ->first();

            if (! $group) {
                $bapdNumber = static::generateBapdNumber($carbonDate);
                $group = static::create([
                    'bapd_number' => $bapdNumber,
                    'approval_date' => $dateString,
                    'status' => 'open',
                ]);
            }

            return $group;
        });
    }

    /**
     * Generate nomor BAPD otomatis dengan format XXX/LAB-BAPD/DD/MM/YYYY.
     * Menggunakan row locking untuk mencegah race condition.
     */
    public static function generateBapdNumber(?Carbon $date = null): string
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

        return "{$sequence}/LAB-BAPD/{$day}/{$month}/{$year}";
    }

    /**
     * Mengecek apakah grup BAPD ini sudah difinalisasi.
     */
    public function isFinalized(): bool
    {
        return $this->status === 'finalized' && $this->finalized_at !== null;
    }

    /**
     * Finalisasi grup BAPD dan bekukan snapshot historis seluruh dokumen yang termasuk di dalamnya.
     */
    public function finalize(): void
    {
        if ($this->isFinalized()) {
            return;
        }

        $this->loadMissing(['destructions.document.category', 'destructions.document.revisions', 'destructions.proposer', 'destructions.approver']);

        $snapshots = [];
        foreach ($this->destructions as $destruction) {
            $doc = $destruction->document;
            $latestRevision = null;
            if ($doc) {
                $latestRevision = $doc->revisions
                    ->whereIn('status', ['Published', 'Terbit'])
                    ->sortByDesc(fn ($rev) => [$rev->revision_date?->timestamp ?? 0, $rev->id])
                    ->first()
                    ?? $doc->revisions
                        ->sortByDesc(fn ($rev) => [$rev->revision_date?->timestamp ?? 0, $rev->id])
                        ->first();
            }

            $publicationDate = '';
            if ($latestRevision?->revision_date) {
                $publicationDate = $latestRevision->revision_date instanceof \DateTimeInterface
                    ? $latestRevision->revision_date->format('d/m/Y')
                    : date('d/m/Y', strtotime((string) $latestRevision->revision_date));
            } elseif ($doc?->publication_year) {
                $publicationDate = (string) $doc->publication_year;
            }

            $revisionNumber = (string) ($latestRevision?->revision_number ?? '');
            if ($revisionNumber !== '' && is_numeric($revisionNumber)) {
                $revisionNumber = str_pad($revisionNumber, 2, '0', STR_PAD_LEFT);
            }

            $snapshots[] = [
                'destruction_id' => $destruction->id,
                'document_number' => $doc?->document_number ?? '',
                'title' => $doc?->title ?? '',
                'category_name' => $doc?->category?->name ?? $doc?->category?->code ?? '',
                'publication_date' => $publicationDate,
                'revision_number' => $revisionNumber,
                'method' => $destruction->method,
                'proposer_name' => $destruction->proposer?->name ?? '',
                'approver_name' => $destruction->approver?->name ?? '',
                'submission_date' => ($destruction->created_at ?? now())->format('d/m/Y'),
                'approval_date' => ($destruction->approved_at ?? $destruction->created_at ?? now())->format('d/m/Y'),
            ];
        }

        $this->update([
            'status' => 'finalized',
            'finalized_at' => now(),
            'snapshot_data' => $snapshots,
        ]);
    }
}
