<?php

namespace App\Filament\Resources\ChangeRequests\Tables;

use App\Services\ChangeRequestExportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->infolist([
                        Section::make('Detail Pengajuan')
                            ->schema([
                                TextEntry::make('document.document_number')
                                    ->label('Dokumen')
                                    ->formatStateUsing(function ($record) {
                                        $document = $record->document;

                                        if (! $document) {
                                            return '-';
                                        }

                                        return $document->document_number . ' - ' . $document->title;
                                    }),
                                TextEntry::make('proposal_date')
                                    ->label('Tanggal Pengajuan')
                                    ->date('d-m-Y'),
                                TextEntry::make('proposer.name')
                                    ->label('Pengusul'),
                                TextEntry::make('reason')
                                    ->label('Alasan Perubahan'),
                                RepeatableEntry::make('proposed_changes')
                                    ->label('Detail Perubahan')
                                    ->schema([
                                        TextEntry::make('halaman')
                                            ->label('Halaman'),
                                        TextEntry::make('item')
                                            ->label('Item'),
                                        TextEntry::make('detail_usulan')
                                            ->label('Detail Usulan'),
                                    ])
                                    ->columns(3),
                            ]),
                    ])
                    ->form(function ($record) {
                        $isManager = auth()->user()?->jabatan === 'manager';

                        if (! $isManager) {
                            return [];
                        }

                        $isPending = $record->approval_status === 'Pending';

                        return [
                            Section::make('Keputusan Approval')
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
                                        ->live()
                                        ->disabled(! $isPending),
                                    Textarea::make('approval_notes')
                                        ->label('Catatan Approval')
                                        ->maxLength(1000)
                                        ->required(fn(Get $get) => $get('approval_status') !== null && $get('approval_status') !== 'Segera dibuat revisi')
                                        ->disabled(! $isPending),
                                ]),
                        ];
                    })
                    ->fillForm(function ($record) {
                        if ($record->approval_status === 'Pending') {
                            return [];
                        }

                        return [
                            'approval_status' => $record->approval_status,
                            'approval_notes' => $record->approval_notes,
                        ];
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
