<?php

namespace App\Notifications;

use App\Models\Document;
use Carbon\Carbon;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentReviewReminder extends Notification
{
    use Queueable;

    /**
     * @param  Document  $document  Dokumen yang mendekati/melewati tenggat kaji ulang
     * @param  Carbon  $dueDate  Tanggal jatuh tempo kaji ulang
     * @param  int  $daysRemaining  Sisa hari menuju tenggat (negatif = overdue)
     * @param  string  $type  Tipe notifikasi: 'panel' (60 hari), 'email' (30 hari), 'overdue'
     */
    public function __construct(
        protected Document $document,
        protected Carbon $dueDate,
        protected int $daysRemaining,
        protected string $type,
    ) {}

    /**
     * Tentukan channel notifikasi.
     * - 'panel' (60 hari): channel database (Filament panel notification)
     * - 'email' (30 hari) dan 'overdue': channel mail
     */
    public function via(object $notifiable): array
    {
        if ($this->type === 'panel') {
            return ['database'];
        }

        return ['database', 'mail'];
    }

    /**
     * Payload notifikasi untuk channel database.
     * Menggunakan format Filament agar muncul di panel notification UI.
     */
    public function toDatabase(object $notifiable): array
    {
        $title = $this->daysRemaining < 0
            ? "Dokumen {$this->document->document_number} telah melewati jadwal kaji ulang"
            : "Dokumen {$this->document->document_number} mendekati jadwal kaji ulang";

        $body = $this->daysRemaining < 0
            ? "{$this->document->title} — terlambat " . abs($this->daysRemaining) . ' hari (jatuh tempo: ' . $this->dueDate->format('d-m-Y') . ')'
            : "{$this->document->title} — sisa {$this->daysRemaining} hari (jatuh tempo: " . $this->dueDate->format('d-m-Y') . ')';

        $icon = $this->daysRemaining < 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-clock';
        $iconColor = $this->daysRemaining < 0 ? 'danger' : 'warning';

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon($icon)
            ->iconColor($iconColor)
            ->getDatabaseMessage();
    }

    /**
     * Notifikasi email untuk channel mail.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage);

        if ($this->type === 'overdue') {
            $daysOverdue = abs($this->daysRemaining);

            $message->subject('Dokumen Overdue: ' . $this->document->document_number)
                ->greeting('Peringatan Kaji Ulang Dokumen Overdue')
                ->line('Dokumen berikut telah melewati tenggat kaji ulang:')
                ->line('Nomor Dokumen: ' . $this->document->document_number)
                ->line('Judul: ' . $this->document->title)
                ->line('Tanggal Jatuh Tempo: ' . $this->dueDate->format('d-m-Y'))
                ->line('Keterlambatan: ' . $daysOverdue . ' hari')
                ->line('Segera lakukan kaji ulang terhadap dokumen ini.');
        } else {
            $message->subject('Pengingat Kaji Ulang: ' . $this->document->document_number)
                ->greeting('Pengingat Kaji Ulang Dokumen')
                ->line('Dokumen berikut mendekati tenggat kaji ulang:')
                ->line('Nomor Dokumen: ' . $this->document->document_number)
                ->line('Judul: ' . $this->document->title)
                ->line('Tanggal Jatuh Tempo: ' . $this->dueDate->format('d-m-Y'))
                ->line('Sisa Waktu: ' . $this->daysRemaining . ' hari')
                ->line('Mohon segera jadwalkan kaji ulang untuk dokumen ini.');
        }

        return $message;
    }
}
