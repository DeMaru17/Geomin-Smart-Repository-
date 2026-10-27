<?php

namespace App\Filament\Resources\DocumentImportBatches\Pages;

use App\Filament\Resources\DocumentImportBatches\DocumentImportBatchResource;
use App\Models\DocumentImportBatch;
use App\Models\DocumentImportItem;
use App\Services\DocumentSync\DocumentMatcherService;
use App\Services\DocumentSync\FilenameParserService;
use App\Services\DocumentSync\FolderScannerService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListDocumentImportBatches extends ListRecords
{
    protected static string $resource = DocumentImportBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scan_directory')
                ->label('Mulai Pemindaian Folder')
                ->icon('heroicon-m-arrow-path')
                ->color('primary')
                ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Pemindaian Folder Server')
                ->modalDescription('Sistem akan memindai seluruh berkas dari direktori impor server yang telah dikonfigurasi. Berkas sumber tidak akan dipindahkan atau diubah.')
                ->modalSubmitActionLabel('Mulai Pindai')
                ->action(function (
                    FolderScannerService $scanner,
                    FilenameParserService $parser,
                    DocumentMatcherService $matcher
                ) {
                    abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                    // 1. Pindai direktori sumber terkonfigurasi (tanpa input path arbitrer)
                    $scanResult = $scanner->scanDirectory();

                    if (! empty($scanResult->errors) && empty($scanResult->files)) {
                        Notification::make()
                            ->title('Pemindaian Gagal')
                            ->body($scanResult->errorMessage ?? 'Direktori sumber tidak dapat dibaca atau tidak ditemukan.')
                            ->danger()
                            ->send();

                        return null;
                    }

                    if (empty($scanResult->files)) {
                        Notification::make()
                            ->title('Tidak Ada Berkas')
                            ->body('Tidak ditemukan berkas dokumen yang memenuhi syarat pada direktori impor server.')
                            ->warning()
                            ->send();

                        return null;
                    }

                    // 2. Parsing seluruh nama berkas hasil pemindaian
                    $parsedFiles = [];
                    foreach ($scanResult->files as $file) {
                        $parsedFiles[] = $parser->parseScannedFile($file);
                    }

                    // 3. Pencocokan batch dengan katalog GSR
                    $matchedResults = $matcher->matchBatch($parsedFiles);

                    // 4. Simpan ke database staging
                    $batchNumber = 'BATCH-' . date('Ymd-His') . '-' . Str::upper(Str::random(4));
                    $status = $scanResult->isPartial ? 'Partial_Scan' : 'Preview_Ready';

                    $batch = DB::connection('mysql')->transaction(function () use ($batchNumber, $status, $scanResult, $matchedResults) {
                        $b = DocumentImportBatch::create([
                            'batch_number' => $batchNumber,
                            'scanned_by' => auth()->id() ?? 1,
                            'source_directory' => 'document-import',
                            'status' => $status,
                            'total_files_scanned' => count($scanResult->files),
                            'total_matched' => 0,
                            'total_installed' => 0,
                            'total_skipped' => 0,
                            'total_failed' => 0,
                        ]);

                        $matchedCount = 0;
                        $skippedCount = 0;

                        foreach ($matchedResults as $res) {
                            if ($res->matchStatus === 'MATCHED') {
                                $matchedCount++;
                            }
                            if (in_array($res->decision, ['SLOT_OCCUPIED', 'CATEGORY_INCOMPATIBLE', 'EXTENSION_REJECTED'], true)) {
                                $skippedCount++;
                            }

                            DocumentImportItem::create([
                                'batch_id' => $b->id,
                                'source_filename' => $res->parsedInfo->filename,
                                'source_relative_path' => $res->parsedInfo->relativePath,
                                'file_extension' => $res->parsedInfo->extension,
                                'file_size_bytes' => $res->parsedInfo->sizeBytes,
                                'file_hash' => $res->parsedInfo->hash,
                                'parsed_document_number' => $res->parsedInfo->documentNumber,
                                'parsed_revision_number' => $res->parsedInfo->revisionNumber,
                                'role_hint' => $res->parsedInfo->roleHint,
                                'matched_document_id' => $res->documentId,
                                'matched_revision_id' => $res->revisionId,
                                'target_slot' => $res->targetSlot,
                                'slot_current_status' => $res->slotCurrentStatus ?? 'UNKNOWN',
                                'match_status' => $res->matchStatus,
                                'decision' => $res->decision,
                                'is_selected' => false, // Default false sesuai spesifikasi desain
                                'install_status' => 'PENDING',
                                'error_message' => $res->errorMessage,
                            ]);
                        }

                        $b->update([
                            'total_matched' => $matchedCount,
                            'total_skipped' => $skippedCount,
                        ]);

                        return $b;
                    });

                    // Notifikasi status pemindaian
                    if ($scanResult->isPartial) {
                        Notification::make()
                            ->title('Pemindaian Parsial Selesai')
                            ->body("Batch {$batch->batch_number} berhasil dibuat: {$batch->total_files_scanned} berkas dipindai ({$batch->total_matched} cocok). Catatan: Pemindaian tidak lengkap karena batas kuota ({$scanResult->partialReason}).")
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Pemindaian Berhasil')
                            ->body("Batch {$batch->batch_number} berhasil dibuat: {$batch->total_files_scanned} berkas dipindai, {$batch->total_matched} cocok.")
                            ->success()
                            ->send();
                    }

                    return redirect(DocumentImportBatchResource::getUrl('view', ['record' => $batch]));
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('batch_number')
                    ->label('Nomor Batch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->label('Waktu Pindai')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'Completed' => 'success',
                        'Preview_Ready' => 'info',
                        'Partial_Scan' => 'warning',
                        'Processing' => 'primary',
                        'Cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('total_files_scanned')
                    ->label('Total Berkas')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('total_matched')
                    ->label('Cocok')
                    ->badge()
                    ->color('info')
                    ->numeric(),

                TextColumn::make('total_installed')
                    ->label('Terpasang')
                    ->badge()
                    ->color('success')
                    ->numeric(),

                TextColumn::make('total_failed')
                    ->label('Gagal')
                    ->badge()
                    ->color('danger')
                    ->numeric(),

                TextColumn::make('scanner.name')
                    ->label('Petugas Pindai')
                    ->placeholder('Sistem / Admin'),
            ])
            ->actions([
                ViewAction::make()
                    ->label('Buka Batch')
                    ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true)),
                DeleteAction::make()
                    ->label('Hapus Batch')
                    ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true)),
            ]);
    }
}
