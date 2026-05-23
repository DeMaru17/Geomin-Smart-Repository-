<?php

namespace Tests\Unit;

use App\Models\Document;
use App\Notifications\DocumentReviewReminder;
use Carbon\Carbon;
use Illuminate\Notifications\Messages\MailMessage;
use PHPUnit\Framework\TestCase;

class DocumentReviewReminderTest extends TestCase
{
    private function makeDocument(): Document
    {
        $document = new Document();
        $document->document_number = 'DOC-001';
        $document->title = 'Prosedur Pengujian Laboratorium';

        return $document;
    }

    public function test_via_returns_database_for_panel_type(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-08-01');

        $notification = new DocumentReviewReminder($document, $dueDate, 55, 'panel');

        $channels = $notification->via((object) []);

        $this->assertEquals(['database'], $channels);
    }

    public function test_via_returns_mail_for_email_type(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-08-01');

        $notification = new DocumentReviewReminder($document, $dueDate, 25, 'email');

        $channels = $notification->via((object) []);

        $this->assertEquals(['mail'], $channels);
    }

    public function test_via_returns_mail_for_overdue_type(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-05-01');

        $notification = new DocumentReviewReminder($document, $dueDate, -10, 'overdue');

        $channels = $notification->via((object) []);

        $this->assertEquals(['mail'], $channels);
    }

    public function test_to_database_returns_correct_payload(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-08-15');

        $notification = new DocumentReviewReminder($document, $dueDate, 45, 'panel');

        $payload = $notification->toDatabase((object) []);

        // Filament format
        $this->assertEquals('filament', $payload['format']);
        $this->assertStringContainsString('DOC-001', $payload['title']);
        $this->assertStringContainsString('Prosedur Pengujian Laboratorium', $payload['body']);
        $this->assertStringContainsString('45 hari', $payload['body']);
        $this->assertEquals('heroicon-o-clock', $payload['icon']);
        $this->assertEquals('warning', $payload['iconColor']);
    }

    public function test_to_database_payload_with_overdue_days(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-05-01');

        $notification = new DocumentReviewReminder($document, $dueDate, -15, 'panel');

        $payload = $notification->toDatabase((object) []);

        // Filament format
        $this->assertEquals('filament', $payload['format']);
        $this->assertStringContainsString('DOC-001', $payload['title']);
        $this->assertStringContainsString('melewati', $payload['title']);
        $this->assertStringContainsString('Prosedur Pengujian Laboratorium', $payload['body']);
        $this->assertStringContainsString('15 hari', $payload['body']);
        $this->assertEquals('heroicon-o-exclamation-triangle', $payload['icon']);
        $this->assertEquals('danger', $payload['iconColor']);
    }

    public function test_to_mail_returns_mail_message_for_email_type(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-08-15');

        $notification = new DocumentReviewReminder($document, $dueDate, 25, 'email');

        $mailMessage = $notification->toMail((object) []);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Pengingat Kaji Ulang: DOC-001', $mailMessage->subject);
    }

    public function test_to_mail_returns_mail_message_for_overdue_type(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-05-01');

        $notification = new DocumentReviewReminder($document, $dueDate, -10, 'overdue');

        $mailMessage = $notification->toMail((object) []);

        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertEquals('Dokumen Overdue: DOC-001', $mailMessage->subject);
    }

    public function test_to_mail_email_type_contains_document_info(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-08-15');

        $notification = new DocumentReviewReminder($document, $dueDate, 25, 'email');

        $mailMessage = $notification->toMail((object) []);

        // Check introLines contain document info
        $lines = implode(' ', $mailMessage->introLines);
        $this->assertStringContainsString('DOC-001', $lines);
        $this->assertStringContainsString('Prosedur Pengujian Laboratorium', $lines);
        $this->assertStringContainsString('15-08-2025', $lines);
        $this->assertStringContainsString('25 hari', $lines);
    }

    public function test_to_mail_overdue_type_contains_overdue_info(): void
    {
        $document = $this->makeDocument();
        $dueDate = Carbon::parse('2025-05-01');

        $notification = new DocumentReviewReminder($document, $dueDate, -10, 'overdue');

        $mailMessage = $notification->toMail((object) []);

        $lines = implode(' ', $mailMessage->introLines);
        $this->assertStringContainsString('DOC-001', $lines);
        $this->assertStringContainsString('Prosedur Pengujian Laboratorium', $lines);
        $this->assertStringContainsString('01-05-2025', $lines);
        $this->assertStringContainsString('10 hari', $lines);
    }
}
