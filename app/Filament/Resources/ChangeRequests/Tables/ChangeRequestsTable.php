<?php

namespace App\Filament\Resources\ChangeRequests\Tables;

use App\Services\ChangeRequestExportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ChangeRequestsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proposal_date')
                    ->label('Tanggal Pengajuan')
                    ->date('d-m-Y')
                    ->sortable(),
                TextColumn::make('document.document_number')
                    ->label('Dokumen')
                    ->formatStateUsing(function ($record) {
                        $document = $record->document;

                        if (! $document) {
                            return '-';
                        }

                        return $document->document_number . ' - ' . $document->title;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('document', function (Builder $q) use ($search) {
                            $q->where('document_number', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                    })
                    ->limit(50),
                TextColumn::make('proposer.name')
                    ->label('Pengusul'),
                TextColumn::make('approval_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'gray',
                        'Segera dibuat revisi' => 'success',
                        'Ditolak' => 'danger',
                        'Di uji coba' => 'warning',
                        'Dibahas di RTM' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('effective_date')
                    ->label('Tanggal Efektif')
                    ->date('d-m-Y')
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('approval_status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'Segera dibuat revisi' => 'Segera dibuat revisi',
                        'Ditolak' => 'Ditolak',
                        'Di uji coba' => 'Di uji coba',
                        'Dibahas di RTM' => 'Dibahas di RTM',
                    ]),
            ])
            ->actions([
                Action::make('review')
                    ->label('Tinjau')
                    ->icon('heroicon-m-eye')
                    ->color('info')
                    ->slideOver()
                    ->visible(fn() => auth()->user()?->jabatan === 'manager')
                    ->form(function ($record) {
                        $document = $record->document;
                        $documentLabel = $document
                            ? $document->document_number . ' - ' . $document->title
                            : '-';

                        // Detail pengajuan (read-only)
                        $detailSchema = [
                            Section::make('Detail Pengajuan')
                                ->schema([
                                    Placeholder::make('detail_dokumen')
                                        ->label('Dokumen')
                                        ->content($documentLabel),
                                    Placeholder::make('detail_tanggal')
                                        ->label('Tanggal Pengajuan')
                                        ->content($record->proposal_date?->format('d-m-Y') ?? '-'),
                                    Placeholder::make('detail_pengusul')
                                        ->label('Pengusul')
                                        ->content($record->proposer?->name ?? '-'),
                                    Placeholder::make('detail_status')
                                        ->label('Status')
                                        ->content(new HtmlString(
                                            '<span class="fi-badge fi-color-custom px-2 py-0.5 text-xs font-medium rounded-md ' . match ($record->approval_status) {
                                                'Pending' => 'fi-color-gray bg-gray-50 text-gray-600 ring-gray-600/10 dark:bg-gray-400/10 dark:text-gray-400 dark:ring-gray-400/20',
                                                'Segera dibuat revisi' => 'fi-color-success bg-success-50 text-success-600 ring-success-600/10 dark:bg-success-400/10 dark:text-success-400 dark:ring-success-400/20',
                                                'Ditolak' => 'fi-color-danger bg-danger-50 text-danger-600 ring-danger-600/10 dark:bg-danger-400/10 dark:text-danger-400 dark:ring-danger-400/20',
                                                'Di uji coba' => 'fi-color-warning bg-warning-50 text-warning-600 ring-warning-600/10 dark:bg-warning-400/10 dark:text-warning-400 dark:ring-warning-400/20',
                                                'Dibahas di RTM' => 'fi-color-info bg-info-50 text-info-600 ring-info-600/10 dark:bg-info-400/10 dark:text-info-400 dark:ring-info-400/20',
                                                default => 'fi-color-gray bg-gray-50 text-gray-600 ring-gray-600/10',
                                            } . ' ring-1 ring-inset">' . e($record->approval_status) . '</span>'
                                        )),
                                    Placeholder::make('detail_alasan')
                                        ->label('Alasan Perubahan')
                                        ->content($record->reason ?? '-')
                                        ->columnSpanFull(),
                                    Repeater::make('proposed_changes')
                                        ->label('Detail Perubahan')
                                        ->schema([
                                            TextInput::make('halaman')
                                                ->label('Halaman')
                                                ->disabled(),
                                            TextInput::make('item')
                                                ->label('Item')
                                                ->disabled(),
                                            Textarea::make('detail_usulan')
                                                ->label('Detail Usulan')
                                                ->disabled()
                                                ->columnSpanFull(),
                                        ])
                                        ->columns(2)
                                        ->columnSpanFull()
                                        ->deletable(false)
                                        ->addable(false)
                                        ->reorderable(false),
                                ])
                                ->columns(2),
                        ];

                        // Form approval hanya untuk manager & status Pending
                        $isManager = auth()->user()?->jabatan === 'manager';
                        $isPending = $record->approval_status === 'Pending';

                        if ($isManager && $isPending) {
                            $detailSchema[] = Section::make('Keputusan Approval')
                                ->schema([
                                    Radio::make('approval_status')
                                        ->label('Status Keputusan')
                                        ->options([
                                            'Segera dibuat revisi' => 'Segera dibuat revisi',
                                            'Ditolak' => 'Ditolak',
                                            'Di uji coba' => 'Di uji coba',
                                            'Dibahas di RTM' => 'Dibahas di RTM',
                                        ])
                                        ->required()
                                        ->live(),
                                    Textarea::make('approval_notes')
                                        ->label('Catatan Approval')
                                        ->maxLength(1000)
                                        ->required(fn(Get $get) => $get('approval_status') !== null && $get('approval_status') !== 'Segera dibuat revisi'),
                                ]);
                        }

                        // Tampilkan hasil approval jika sudah diproses
                        if (! $isPending) {
                            $detailSchema[] = Section::make('Hasil Approval')
                                ->schema([
                                    Placeholder::make('detail_approver')
                                        ->label('Disetujui/Ditolak Oleh')
                                        ->content($record->approver?->name ?? '-'),
                                    Placeholder::make('detail_effective_date')
                                        ->label('Tanggal Efektif')
                                        ->content($record->effective_date?->format('d-m-Y') ?? '-'),
                                    Placeholder::make('detail_approval_notes')
                                        ->label('Catatan Approval')
                                        ->content($record->approval_notes ?? '-')
                                        ->columnSpanFull(),
                                ])
                                ->columns(2);
                        }

                        return $detailSchema;
                    })
                    ->fillForm(function ($record) {
                        $data = [
                            'proposed_changes' => $record->proposed_changes ?? [],
                        ];

                        if ($record->approval_status !== 'Pending') {
                            $data['approval_status'] = $record->approval_status;
                            $data['approval_notes'] = $record->approval_notes;
                        }

                        return $data;
                    })
                    ->action(function ($record, array $data) {
                        // Server-side authorization check
                        if (auth()->user()?->jabatan !== 'manager') {
                            Notification::make()
                                ->title('Tidak Diizinkan')
                                ->body('Hanya manager yang dapat melakukan approval.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Prevent re-submission for already-approved requests
                        if ($record->approval_status !== 'Pending') {
                            Notification::make()
                                ->title('Tidak Dapat Diproses')
                                ->body('Pengajuan ini sudah diproses sebelumnya.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $record->update([
                            'approval_status' => $data['approval_status'],
                            'approval_notes' => $data['approval_notes'] ?? null,
                            'approver_id' => auth()->id(),
                            'effective_date' => now(),
                        ]);

                        Notification::make()
                            ->title('Berhasil')
                            ->body('Keputusan approval berhasil disimpan.')
                            ->success()
                            ->send();
                    })
                    ->modalSubmitAction(function ($record) {
                        $isManager = auth()->user()?->jabatan === 'manager';
                        $isPending = $record->approval_status === 'Pending';

                        if (! $isManager || ! $isPending) {
                            return false;
                        }

                        return null;
                    }),
                Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn($record) => auth()->user()?->can('export', $record))
                    ->action(function ($record) {
                        // Server-side policy check
                        if (! auth()->user()?->can('export', $record)) {
                            Notification::make()
                                ->title('Tidak Diizinkan')
                                ->body('Anda tidak memiliki akses untuk mengekspor.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $service = new ChangeRequestExportService;
                        $result = $service->export($record);

                        if ($result === false) {
                            Notification::make()
                                ->title('File template tidak tersedia')
                                ->danger()
                                ->send();

                            return;
                        }

                        $filename = $service->getFilename($record);

                        return response()->download($result, $filename)->deleteFileAfterSend();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }
}
