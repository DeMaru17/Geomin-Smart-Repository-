<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentReviewNotification;
use App\Models\User;
use App\Notifications\DocumentReviewReminder;
use App\Services\DocumentReviewService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CheckDocumentReviews extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'app:check-document-reviews';

    /**
     * The console command description.
     */
    protected $description = 'Scan dokumen aktif dan kirim notifikasi kaji ulang berdasarkan threshold';

    /**
     * Execute the console command.
     */
    public function handle(DocumentReviewService $service): int
    {
        $this->info('Memulai pengecekan jadwal kaji ulang dokumen...');

        // Get all active documents (those with at least one Published/Terbit revision)
        $documents = Document::with(['revisions', 'reviews'])
            ->whereHas('revisions', function ($query) {
                $query->whereIn('status', ['Published', 'Terbit']);
            })
            ->get();

        $this->info("Ditemukan {$documents->count()} dokumen aktif.");

        $notifiedCount = 0;
        $skippedCount = 0;
        $errorCount = 0;

        foreach ($documents as $document) {
            try {
                $daysRemaining = $service->getDaysRemaining($document);
                $dueDate = $service->getDueDate($document);

                // Determine notification type based on thresholds
                $type = $this->determineNotificationType($daysRemaining);

                // Skip if no notification needed (more than 60 days remaining)
                if ($type === null) {
                    $skippedCount++;

                    continue;
                }

                // Check deduplication
                $notificationRecord = DocumentReviewNotification::firstOrCreate(
                    [
                        'document_id' => $document->id,
                        'due_date' => $dueDate->toDateString(),
                    ]
                );

                if ($this->isAlreadyNotified($notificationRecord, $type)) {
                    $skippedCount++;

                    continue;
                }

                // Get all users with role 'admin' or 'manajemen'
                $recipients = User::whereIn('role', ['admin', 'manajemen'])->get();

                if ($recipients->isEmpty()) {
                    $this->warn("Tidak ada penerima notifikasi (admin/manajemen) untuk dokumen {$document->document_number}.");
                    $skippedCount++;

                    continue;
                }

                // Send notification
                Notification::send(
                    $recipients,
                    new DocumentReviewReminder($document, $dueDate, $daysRemaining, $type)
                );

                // Update notified_at timestamp AFTER successful dispatch
                $this->markAsNotified($notificationRecord, $type);

                $notifiedCount++;
                $this->line("  [{$type}] {$document->document_number} - sisa {$daysRemaining} hari");
            } catch (\Throwable $e) {
                $errorCount++;
                Log::error("CheckDocumentReviews: Gagal memproses dokumen ID {$document->id}", [
                    'document_id' => $document->id,
                    'document_number' => $document->document_number ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  Error pada dokumen ID {$document->id}: {$e->getMessage()}");

                continue;
            }
        }

        $this->info("Selesai. Notifikasi terkirim: {$notifiedCount}, Dilewati: {$skippedCount}, Error: {$errorCount}");

        return self::SUCCESS;
    }

    /**
     * Determine notification type based on days remaining threshold.
     */
    protected function determineNotificationType(int $daysRemaining): ?string
    {
        if ($daysRemaining <= 0) {
            return 'overdue';
        }

        if ($daysRemaining <= 30) {
            return 'email';
        }

        if ($daysRemaining <= 60) {
            return 'panel';
        }

        return null;
    }

    /**
     * Check if notification has already been sent for this type.
     */
    protected function isAlreadyNotified(DocumentReviewNotification $record, string $type): bool
    {
        return match ($type) {
            'panel' => $record->panel_notified_at !== null,
            'email' => $record->email_notified_at !== null,
            'overdue' => $record->overdue_notified_at !== null,
            default => false,
        };
    }

    /**
     * Mark the notification record as notified for the given type.
     */
    protected function markAsNotified(DocumentReviewNotification $record, string $type): void
    {
        $column = match ($type) {
            'panel' => 'panel_notified_at',
            'email' => 'email_notified_at',
            'overdue' => 'overdue_notified_at',
        };

        $record->update([$column => now()]);
    }
}
