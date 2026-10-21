<?php

namespace App\Filament\Resources\DocumentDestructions\Tables;

use App\Models\DocumentDestructionGroup;
use App\Models\DocumentRevision;
use App\Services\DocumentDestructionExportService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentDestructionsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bapd_number')
                    ->label('Nomor BAPD')
                    ->searchable()
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
                    ->limit(50)
                    ->placeholder('-'),

                TextColumn::make('method')
                    ->label('Metode'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'gray',
                        'Disetujui' => 'success',
                        'Ditolak' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('proposer.name')
                    ->label('Pengusul')
                    ->placeholder('-'),

                TextColumn::make('created_at')
                    ->label('Tanggal Pengajuan')
                    ->date('d-m-Y')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'Disetujui' => 'Disetujui',
                        'Ditolak' => 'Ditolak',
                    ]),
            ])
            ->actions([
                Action::make('review')
                    ->label('Tinjau')
                    ->icon('heroicon-m-clipboard-document-check')
                    ->color('info')
                    ->slideOver()
                    ->visible(fn($record) => auth()->user()?->jabatan === 'manager' && $record->status === 'Pending')
                    ->form(function ($record) {
                        $document = $record->document;
                        $documentNumber = $document?->document_number ?? '-';
                        $documentTitle = $document?->title ?? '-';

                        return [
                            Section::make('Ringkasan Pengajuan')
                                ->schema([
                                    Placeholder::make('detail_bapd_number')
                                        ->label('Nomor BAPD')
                                        ->content($record->bapd_number),
                                    Placeholder::make('detail_document_number')
                                        ->label('Nomor Dokumen')
                                        ->content($documentNumber),
                                    Placeholder::make('detail_document_title')
                                        ->label('Judul Dokumen')
                                        ->content($documentTitle),
                                    Placeholder::make('detail_proposer')
                                        ->label('Nama Pengusul')
                                        ->content($record->proposer?->name ?? '-'),
                                    Placeholder::make('detail_reason')
                                        ->label('Alasan Pemusnahan')
                                        ->content($record->reason ?? '-')
                                        ->columnSpanFull(),
                                    Placeholder::make('detail_method')
                                        ->label('Metode Penarikan')
                                        ->content($record->method ?? '-'),
                                ])
                                ->columns(2),

                            Section::make('Keputusan')
                                ->schema([
                                    Radio::make('decision')
                                        ->label('Keputusan Review')
                                        ->options([
                                            'Setujui' => 'Setujui',
                                            'Tolak' => 'Tolak',
                                        ])
                                        ->required(),
                                ]),
                        ];
                    })
                    ->action(function ($record, array $data) {
                        // Server-side authorization check
                        if (auth()->user()?->jabatan !== 'manager') {
                            Notification::make()
                                ->title('Tidak Diizinkan')
                                ->body('Anda tidak memiliki wewenang untuk melakukan review.')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Server-side status check
                        if ($record->status !== 'Pending') {
                            Notification::make()
                                ->title('Tidak Dapat Diproses')
                                ->body('Pengajuan sudah diproses sebelumnya.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $decision = $data['decision'];

                        if ($decision === 'Setujui') {
                            // Update BAPD status
                            $record->update([
                                'status' => 'Disetujui',
                                'approver_id' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            // Update document status to Obsolete
                            if ($record->document) {
                                $record->document->update(['status' => 'Obsolete']);
                            }

                            // Find latest published/terbit revision and set to Obsolete
                            $latestRevision = DocumentRevision::where('document_id', $record->document_id)
                                ->whereIn('status', ['Published', 'Terbit'])
                                ->latest('created_at')
                                ->first();

                            if ($latestRevision) {
                                $latestRevision->update(['status' => 'Obsolete']);
                            }

                            Notification::make()
                                ->title('Berhasil')
                                ->body('Pengajuan pemusnahan telah disetujui.')
                                ->success()
                                ->send();
                        } else {
                            // Tolak - only update BAPD, no changes to document/revision
                            $record->update([
                                'status' => 'Ditolak',
                                'approver_id' => auth()->id(),
                                'approved_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Berhasil')
                                ->body('Pengajuan pemusnahan telah ditolak.')
                                ->success()
                                ->send();
                        }
                    }),

                DeleteAction::make()
                    ->visible(function ($record) {
                        $user = auth()->user();

                        return in_array($user?->role, ['admin', 'manajemen']) && $record->status === 'Pending';
                    })
                    ->before(function ($record, DeleteAction $action) {
                        if ($record->status !== 'Pending') {
                            Notification::make()
                                ->title('Tidak Dapat Dihapus')
                                ->body('Record dengan status ini tidak dapat dihapus.')
                                ->danger()
                                ->send();

                            $action->cancel();
                        }
                    }),
            ])
            ->headerActions([
                Action::make('cetak_bapd_tanggal')
                    ->label('Cetak BAPD per Tanggal')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->visible(fn () => in_array(auth()->user()?->role, ['admin', 'manajemen']))
                    ->modalHeading('Cetak BAPD')
                    ->modalDescription('Pilih tanggal yang ditandai pada kalender atau cari BAPD melalui daftar.')
                    ->modalWidth('5xl')
                    ->modalContent(view('filament.resources.document-destructions.bapd-modal'))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Belum ada data pemusnahan dokumen')
            ->emptyStateDescription('Buat pengajuan pemusnahan dokumen baru untuk memulai.');
    }
}
