<?php

namespace App\Filament\Resources\DocumentImportBatches\RelationManagers;

use App\Models\Document;
use App\Models\DocumentImportItem;
use App\Models\DocumentRevision;
use App\Services\DocumentSync\DocumentInstallerService;
use App\Services\DocumentSync\DTO\InstallItemResult;
use App\Services\DocumentSync\DTO\RecoverItemResult;
use App\Services\DocumentSync\PlaceholderDetectorService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\CheckboxColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $modelLabel = 'Berkas Impor';

    protected static ?string $pluralModelLabel = 'Daftar Berkas Impor';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'manajemen'], true);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('source_filename')
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('source_filename')
                    ->label('Nama Berkas Sumber')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->wrap(),

                TextColumn::make('file_extension')
                    ->label('Ekstensi')
                    ->badge()
                    ->color(fn(string $state): string => match (strtolower($state)) {
                        'pdf' => 'danger',
                        'docx', 'doc' => 'info',
                        'xlsx', 'xls' => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('parsed_document_number')
                    ->label('No. Dok (Parse)')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('parsed_revision_number')
                    ->label('Rev (Parse)')
                    ->placeholder('-'),

                TextColumn::make('document.document_number')
                    ->label('Dokumen Target')
                    ->searchable()
                    ->placeholder('-')
                    ->color('primary'),

                TextColumn::make('revision.revision_number')
                    ->label('Rev Target')
                    ->placeholder('-'),

                TextColumn::make('target_slot')
                    ->label('Slot Target')
                    ->badge()
                    ->formatStateUsing(fn(?string $state) => match ($state) {
                        'file_path' => 'Berkas Utama (PDF/Blanko)',
                        'word_file_path' => 'Berkas Word/Master',
                        default => '-',
                    })
                    ->color('gray'),

                TextColumn::make('match_status')
                    ->label('Pencocokan')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'MATCHED' => 'success',
                        'AMBIGUOUS_MATCH' => 'warning',
                        'CONFLICT_DUPLICATE_SOURCE' => 'danger',
                        'DOC_NOT_FOUND', 'REV_NOT_FOUND' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('slot_current_status')
                    ->label('Status Slot')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        PlaceholderDetectorService::STATUS_EMPTY => 'gray',
                        PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL,
                        PlaceholderDetectorService::STATUS_PLACEHOLDER_EXTERNAL => 'warning',
                        PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS => 'success',
                        'SLOT_OCCUPIED_LOCKED' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('decision')
                    ->label('Keputusan')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'READY_TO_ATTACH', 'PLACEHOLDER_REPLACEABLE', 'MANUAL_RESOLVED' => 'success',
                        'AMBIGUOUS_MATCH', 'MANUAL_REVIEW_REQUIRED', 'AMBIGUOUS_REVISION' => 'warning',
                        'SLOT_OCCUPIED', 'CONFLICT_DUPLICATE_SOURCE', 'CATEGORY_INCOMPATIBLE', 'EXTENSION_REJECTED' => 'danger',
                        default => 'gray',
                    }),

                CheckboxColumn::make('is_selected')
                    ->label('Pilih')
                    ->disabled(fn(DocumentImportItem $record) => ! in_array(auth()->user()?->role, ['admin', 'manajemen'], true)
                        || ! $record->isEligibleForInstallation()
                        || $record->install_status === InstallItemResult::STATUS_SUCCESS),

                TextColumn::make('install_status')
                    ->label('Status Pasang')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        InstallItemResult::STATUS_SUCCESS => 'success',
                        InstallItemResult::STATUS_PROCESSING => 'warning',
                        InstallItemResult::STATUS_FAILED => 'danger',
                        InstallItemResult::STATUS_SKIPPED => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('error_message')
                    ->label('Catatan / Error')
                    ->limit(35)
                    ->tooltip(fn(DocumentImportItem $record) => $record->error_message)
                    ->color('danger')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('decision')
                    ->label('Keputusan')
                    ->options([
                        'READY_TO_ATTACH' => 'READY_TO_ATTACH',
                        'PLACEHOLDER_REPLACEABLE' => 'PLACEHOLDER_REPLACEABLE',
                        'MANUAL_RESOLVED' => 'MANUAL_RESOLVED',
                        'AMBIGUOUS_MATCH' => 'AMBIGUOUS_MATCH',
                        'MANUAL_REVIEW_REQUIRED' => 'MANUAL_REVIEW_REQUIRED',
                        'CONFLICT_DUPLICATE_SOURCE' => 'CONFLICT_DUPLICATE_SOURCE',
                        'SLOT_OCCUPIED' => 'SLOT_OCCUPIED',
                        'CATEGORY_INCOMPATIBLE' => 'CATEGORY_INCOMPATIBLE',
                        'DOC_NOT_FOUND' => 'DOC_NOT_FOUND',
                        'REV_NOT_FOUND' => 'REV_NOT_FOUND',
                    ]),

                SelectFilter::make('match_status')
                    ->label('Status Pencocokan')
                    ->options([
                        'MATCHED' => 'MATCHED',
                        'AMBIGUOUS_MATCH' => 'AMBIGUOUS_MATCH',
                        'CONFLICT_DUPLICATE_SOURCE' => 'CONFLICT_DUPLICATE_SOURCE',
                        'DOC_NOT_FOUND' => 'DOC_NOT_FOUND',
                        'REV_NOT_FOUND' => 'REV_NOT_FOUND',
                        'PENDING' => 'PENDING',
                    ]),

                SelectFilter::make('slot_current_status')
                    ->label('Status Slot Fisik')
                    ->options([
                        PlaceholderDetectorService::STATUS_EMPTY => 'SLOT_EMPTY (Kosong)',
                        PlaceholderDetectorService::STATUS_PLACEHOLDER_INTERNAL => 'SLOT_SEEDER_PLACEHOLDER (Placeholder)',
                        PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS => 'SLOT_PHYSICAL_EXISTS (Sudah Terisi Berkas Fisik)',
                        'SLOT_OCCUPIED_LOCKED' => 'SLOT_OCCUPIED_LOCKED (Terkunci)',
                    ]),

                SelectFilter::make('install_status')
                    ->label('Status Pemasangan')
                    ->options([
                        'PENDING' => 'PENDING',
                        InstallItemResult::STATUS_SUCCESS => 'ATTACHED',
                        InstallItemResult::STATUS_PROCESSING => 'PROCESSING',
                        InstallItemResult::STATUS_FAILED => 'FAILED',
                        InstallItemResult::STATUS_SKIPPED => 'SKIPPED',
                    ]),

                SelectFilter::make('file_extension')
                    ->label('Ekstensi Berkas')
                    ->options([
                        'pdf' => 'PDF',
                        'docx' => 'DOCX',
                        'doc' => 'DOC',
                        'xlsx' => 'XLSX',
                        'xls' => 'XLS',
                    ]),

                TernaryFilter::make('is_selected')
                    ->label('Status Seleksi')
                    ->placeholder('Semua')
                    ->trueLabel('Hanya yang Dipilih')
                    ->falseLabel('Belum Dipilih'),
            ], layout: FiltersLayout::AboveContent)
            ->actions([
                // 1. Aksi Penyelesaian Manual
                Action::make('resolve_manual')
                    ->label('Selesaikan Manual')
                    ->icon('heroicon-m-wrench-screwdriver')
                    ->color('warning')
                    ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                    ->visible(fn(DocumentImportItem $record) => $record->install_status !== InstallItemResult::STATUS_SUCCESS && $record->install_status !== InstallItemResult::STATUS_PROCESSING)
                    ->modalHeading('Penyelesaian Manual Target Dokumen')
                    ->modalDescription(fn(DocumentImportItem $record) => "Tentukan target dokumen, revisi, dan slot untuk berkas: {$record->source_filename}")
                    ->modalSubmitActionLabel('Simpan Target')
                    ->schema([
                        Select::make('matched_document_id')
                            ->label('Pilih Dokumen GSR')
                            ->options(fn() => Document::query()->orderBy('document_number')->get()->mapWithKeys(fn($d) => [$d->id => "{$d->document_number} — {$d->title}"]))
                            ->searchable()
                            ->live()
                            ->required()
                            ->default(fn(DocumentImportItem $record) => $record->matched_document_id),

                        Select::make('matched_revision_id')
                            ->label('Pilih Revisi Dokumen')
                            ->options(function ($get) {
                                $docId = $get('matched_document_id');
                                if (! $docId) {
                                    return [];
                                }

                                return DocumentRevision::where('document_id', $docId)
                                    ->orderByDesc('revision_number')
                                    ->get()
                                    ->mapWithKeys(fn($r) => [$r->id => "Rev {$r->revision_number} (Status: {$r->status})"]);
                            })
                            ->required()
                            ->live()
                            ->default(fn(DocumentImportItem $record) => $record->matched_revision_id),

                        Select::make('target_slot')
                            ->label('Slot Target')
                            ->options([
                                'file_path' => 'file_path (Dokumen PDF / Blanko Formulir Excel)',
                                'word_file_path' => 'word_file_path (Master Word / Master Formulir Excel)',
                            ])
                            ->required()
                            ->default(fn(DocumentImportItem $record) => $record->target_slot ?? ($record->file_extension === 'pdf' ? 'file_path' : 'word_file_path')),
                    ])
                    ->action(function (DocumentImportItem $record, array $data, PlaceholderDetectorService $detector) {
                        abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                        if ($record->install_status === InstallItemResult::STATUS_SUCCESS || $record->install_status === InstallItemResult::STATUS_PROCESSING) {
                            Notification::make()->title('Tindakan Ditolak')->body('Berkas yang sudah terpasang atau sedang diproses tidak dapat diubah targetnya.')->danger()->send();

                            return;
                        }

                        // 1. Validasi keberadaan doc dan rev
                        $doc = Document::find($data['matched_document_id']);
                        $rev = DocumentRevision::find($data['matched_revision_id']);

                        if (! $doc || ! $rev) {
                            Notification::make()->title('Target Tidak Valid')->body('Dokumen atau revisi tidak ditemukan di database.')->danger()->send();

                            return;
                        }

                        if ($rev->document_id !== $doc->id) {
                            Notification::make()->title('Target Tidak Valid')->body('Revisi yang dipilih bukan bagian dari dokumen yang dipilih.')->danger()->send();

                            return;
                        }

                        // 2. Validasi aturan pemetaan kategori dan slot
                        $catCode = $doc->category?->code ?? ($doc->is_external ? 'EXTERNAL' : null);
                        $ext = strtolower($record->file_extension);
                        $targetSlot = $data['target_slot'];
                        $newRoleHint = $record->role_hint;

                        if (in_array($catCode, ['PK', 'IK'], true)) {
                            if ($ext === 'pdf' && $targetSlot !== 'file_path') {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body("Berkas PDF kategori {$catCode} hanya boleh dipasang ke slot file_path.")->danger()->send();

                                return;
                            }
                            if (in_array($ext, ['doc', 'docx'], true) && $targetSlot !== 'word_file_path') {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body("Berkas Word kategori {$catCode} hanya boleh dipasang ke slot word_file_path.")->danger()->send();

                                return;
                            }
                            if (! in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body("Ekstensi [{$ext}] tidak diizinkan untuk kategori {$catCode}.")->danger()->send();

                                return;
                            }
                        } elseif ($catCode === 'F') {
                            if ($ext === 'pdf') {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body('Berkas PDF tidak diizinkan untuk kategori Formulir F.')->danger()->send();

                                return;
                            }
                            if (in_array($ext, ['xls', 'xlsx'], true)) {
                                if ($targetSlot !== 'file_path') {
                                    Notification::make()->title('Aturan Pemetaan Dilanggar')->body('Berkas Excel (.xls, .xlsx) untuk dokumen Formulir harus masuk ke slot file_path (Blanko).')->danger()->send();

                                    return;
                                }
                                $newRoleHint = 'blanko';
                            } elseif (in_array($ext, ['doc', 'docx'], true)) {
                                if ($targetSlot !== 'word_file_path') {
                                    Notification::make()->title('Aturan Pemetaan Dilanggar')->body('Berkas Word (.doc, .docx) untuk dokumen Formulir harus masuk ke slot word_file_path (Master).')->danger()->send();

                                    return;
                                }
                                $newRoleHint = 'master';
                            } else {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body("Ekstensi [{$ext}] tidak diizinkan untuk kategori Formulir F.")->danger()->send();

                                return;
                            }
                        } elseif ($catCode === 'EXTERNAL') {
                            if ($ext !== 'pdf' || $targetSlot !== 'file_path') {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body('Dokumen Eksternal hanya menerima berkas PDF pada slot file_path.')->danger()->send();

                                return;
                            }
                        } elseif ($catCode === 'PM') {
                            if ($ext === 'pdf' && $targetSlot !== 'file_path') {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body('Berkas PDF Panduan Mutu (PM) hanya boleh dipasang ke slot file_path.')->danger()->send();

                                return;
                            }
                            if (in_array($ext, ['doc', 'docx'], true) && $targetSlot !== 'word_file_path') {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body('Berkas Word Panduan Mutu (PM) hanya boleh dipasang ke slot word_file_path.')->danger()->send();

                                return;
                            }
                            if (! in_array($ext, ['pdf', 'doc', 'docx'], true)) {
                                Notification::make()->title('Aturan Pemetaan Dilanggar')->body("Ekstensi [{$ext}] tidak diizinkan untuk kategori Panduan Mutu (PM).")->danger()->send();

                                return;
                            }
                        }

                        // 3. Validasi pencegahan konflik target: apakah slot pada revisi ini sudah dipasang oleh item lain?
                        $alreadyAttached = DocumentImportItem::where('matched_revision_id', $rev->id)
                            ->where('target_slot', $targetSlot)
                            ->where('id', '!=', $record->id)
                            ->where('install_status', InstallItemResult::STATUS_SUCCESS)
                            ->exists();

                        if ($alreadyAttached) {
                            Notification::make()->title('Slot Sudah Terisi')->body("Slot target [{$targetSlot}] pada revisi ini sudah berhasil dipasang oleh berkas lain.")->danger()->send();

                            return;
                        }

                        // 4. Validasi fisik slot target (dilarang menimpa berkas fisik storage)
                        $currentSlotVal = $targetSlot === 'word_file_path' ? $rev->word_file_path : $rev->file_path;
                        $slotStatus = $detector->detectPath($currentSlotVal, config('document_sync.disk', 'local'));

                        if ($slotStatus === PlaceholderDetectorService::STATUS_PHYSICAL_EXISTS) {
                            Notification::make()->title('Slot Sudah Terisi')->body("Slot target [{$targetSlot}] saat ini sudah memiliki berkas fisik valid pada storage (dilarang menimpa).")->danger()->send();

                            return;
                        }

                        // 5. Update data model staging secara konsisten
                        $record->update([
                            'matched_document_id' => $doc->id,
                            'matched_revision_id' => $rev->id,
                            'target_slot' => $targetSlot,
                            'role_hint' => $newRoleHint,
                            'slot_current_status' => $slotStatus,
                            'match_status' => 'MATCHED',
                            'decision' => 'MANUAL_RESOLVED',
                            'error_message' => 'Diselesaikan secara manual oleh ' . (auth()->user()?->name ?? 'Admin'),
                            'is_selected' => true,
                        ]);

                        $slotLabel = $targetSlot === 'word_file_path' ? 'Berkas Word/Master' : 'Berkas Utama (PDF/Blanko)';
                        Notification::make()
                            ->title('Penyelesaian Manual Berhasil')
                            ->body("Item berhasil dipetakan ke Dokumen {$doc->document_number} Rev {$rev->revision_number} ({$slotLabel}).")
                            ->success()
                            ->send();
                    }),

                // 2. Aksi Pasang Berkas Satuan
                Action::make('install')
                    ->label('Pasang')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                    ->visible(fn(DocumentImportItem $record) => $record->isEligibleForInstallation() && $record->install_status !== InstallItemResult::STATUS_SUCCESS && $record->install_status !== InstallItemResult::STATUS_PROCESSING)
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pemasangan Berkas')
                    ->modalDescription(fn(DocumentImportItem $record) => "Pasang berkas [{$record->source_filename}] ke Dokumen {$record->document?->document_number} Rev {$record->revision?->revision_number}?")
                    ->action(function (DocumentImportItem $record, DocumentInstallerService $installer) {
                        abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                        if ($record->install_status === InstallItemResult::STATUS_SUCCESS) {
                            Notification::make()->title('Pemasangan Ditolak')->body('Berkas ini sudah berstatus ATTACHED dan tidak dapat dipasang ulang.')->warning()->send();

                            return;
                        }

                        if ($record->install_status === InstallItemResult::STATUS_PROCESSING) {
                            Notification::make()->title('Pemasangan Ditolak')->body('Berkas sedang diproses oleh permintaan lain. Gunakan aksi pemulihan jika proses terhenti.')->warning()->send();

                            return;
                        }

                        $result = $installer->installItem($record);

                        // Perbarui counter statistik batch induk secara konsisten
                        $batch = $record->batch;
                        if ($batch) {
                            $batch->refresh();
                            $batch->total_installed = $batch->items()->where('install_status', InstallItemResult::STATUS_SUCCESS)->count();
                            $batch->total_failed = $batch->items()->whereNotIn('install_status', [InstallItemResult::STATUS_PENDING, InstallItemResult::STATUS_PROCESSING, InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count();
                            if ($batch->items()->whereNotIn('install_status', [InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count() === 0) {
                                $batch->status = 'Completed';
                                $batch->completed_at = now();
                            }
                            $batch->save();
                        }

                        if ($result->isSuccess) {
                            Notification::make()->title('Pemasangan Berhasil')->body("Berkas [{$record->source_filename}] berhasil dipasang.")->success()->send();
                        } else {
                            Notification::make()->title('Pemasangan Gagal')->body($result->errorMessage)->danger()->send();
                        }
                    }),

                // 3. Aksi Pulihkan Status PROCESSING Satuan
                Action::make('recover')
                    ->label('Pulihkan')
                    ->icon('heroicon-m-shield-check')
                    ->color('warning')
                    ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                    ->visible(fn(DocumentImportItem $record) => $record->install_status === InstallItemResult::STATUS_PROCESSING)
                    ->requiresConfirmation()
                    ->modalHeading('Pemulihan Status Berkas Tertinggal')
                    ->modalDescription('Sistem akan memeriksa konsistensi database dan berkas fisik untuk item ini. Jika proses masih aktif (< 5 menit), pemulihan akan dilewati secara aman.')
                    ->action(function (DocumentImportItem $record, DocumentInstallerService $installer) {
                        abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                        $res = $installer->recoverStuckItem($record, staleSeconds: 300, force: false);

                        // Perbarui counter statistik batch induk secara konsisten
                        $batch = $record->batch;
                        if ($batch) {
                            $batch->refresh();
                            $batch->total_installed = $batch->items()->where('install_status', InstallItemResult::STATUS_SUCCESS)->count();
                            $batch->total_failed = $batch->items()->whereNotIn('install_status', [InstallItemResult::STATUS_PENDING, InstallItemResult::STATUS_PROCESSING, InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count();
                            $batch->save();
                        }

                        if ($res->wasRecovered) {
                            Notification::make()->title('Pemulihan Berhasil')->body($res->message)->info()->send();
                        } else {
                            Notification::make()->title('Pemulihan Dilewati')->body($res->message)->warning()->send();
                        }
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('select_eligible')
                        ->label('Tandai Dipilih (Hanya yang Siap)')
                        ->icon('heroicon-m-check')
                        ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                        ->action(function (Collection $records) {
                            abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                            $count = 0;
                            foreach ($records as $record) {
                                if ($record->isEligibleForInstallation() && $record->install_status !== InstallItemResult::STATUS_SUCCESS) {
                                    $record->update(['is_selected' => true]);
                                    $count++;
                                }
                            }
                            Notification::make()->title('Seleksi Diperbarui')->body("{$count} berkas berhasil ditandai untuk dipasang.")->success()->send();
                        }),

                    BulkAction::make('unselect_all')
                        ->label('Batalkan Pilihan')
                        ->icon('heroicon-m-x-mark')
                        ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                        ->action(function (Collection $records) {
                            abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                            $count = 0;
                            foreach ($records as $record) {
                                $record->update(['is_selected' => false]);
                                $count++;
                            }
                            Notification::make()->title('Seleksi Dibatalkan')->body("{$count} berkas dibatalkan pilihannya.")->info()->send();
                        }),

                    BulkAction::make('install_selected_bulk')
                        ->label('Pasang Berkas Terpilih')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->color('success')
                        ->authorize(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'], true))
                        ->requiresConfirmation()
                        ->modalHeading('Konfirmasi Pemasangan Massal')
                        ->modalDescription('Hanya berkas yang memenuhi syarat dan berstatus dipilih yang akan dipasang.')
                        ->action(function (Collection $records, DocumentInstallerService $installer) {
                            abort_unless(in_array(auth()->user()?->role, ['admin', 'manajemen'], true), 403, 'Aksi ini hanya dapat dilakukan oleh Admin atau Manajemen.');

                            $success = 0;
                            $failed = 0;

                            foreach ($records as $record) {
                                if ($record->is_selected && $record->isEligibleForInstallation() && $record->install_status !== InstallItemResult::STATUS_SUCCESS) {
                                    $res = $installer->installItem($record);
                                    if ($res->isSuccess) {
                                        $success++;
                                    } else {
                                        $failed++;
                                    }
                                }
                            }

                            // Perbarui counter statistik batch induk
                            $batch = $this->getOwnerRecord();
                            if ($batch) {
                                $batch->refresh();
                                $batch->total_installed = $batch->items()->where('install_status', InstallItemResult::STATUS_SUCCESS)->count();
                                $batch->total_failed = $batch->items()->whereNotIn('install_status', [InstallItemResult::STATUS_PENDING, InstallItemResult::STATUS_PROCESSING, InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count();
                                if ($batch->items()->whereNotIn('install_status', [InstallItemResult::STATUS_SUCCESS, 'SKIPPED'])->count() === 0) {
                                    $batch->status = 'Completed';
                                    $batch->completed_at = now();
                                }
                                $batch->save();
                            }

                            Notification::make()
                                ->title('Pemasangan Massal Selesai')
                                ->body("Hasil: {$success} berhasil dipasang, {$failed} gagal.")
                                ->color($failed > 0 ? 'warning' : 'success')
                                ->send();
                        }),
                ]),
            ]);
    }
}
