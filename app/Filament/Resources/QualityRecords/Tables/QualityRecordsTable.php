<?php

namespace App\Filament\Resources\QualityRecords\Tables;

use App\Filament\Resources\QualityRecords\QualityRecordResource;
use App\Models\Document;
use App\Models\QualityRecord;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class QualityRecordsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('record_name')
                    ->label('Nama Rekaman')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('record_date')
                    ->label('Tanggal Rekaman')
                    ->date('d-m-Y')
                    ->sortable(),

                TextColumn::make('expiration_date')
                    ->label('Tanggal Kedaluwarsa')
                    ->date('d-m-Y')
                    ->placeholder('-')
                    ->badge()
                    ->color(fn($state): ?string => $state && Carbon::parse($state)->isPast() ? 'danger' : null),

                TextColumn::make('physical_location')
                    ->label('Lokasi Fisik')
                    ->placeholder('-'),

                TextColumn::make('uploader.name')
                    ->label('Pencatat')
                    ->placeholder('-'),
            ])
            ->searchable(['document.title'])
            ->defaultGroup(
                Group::make('document.document_number')
                    ->label('Formulir Master')
                    ->getTitleFromRecordUsing(
                        fn($record): string => $record->document
                            ? "{$record->document->document_number} - {$record->document->title}"
                            : '-'
                    )
                    ->orderQueryUsing(fn(Builder $query, string $direction): Builder => $query->orderBy(
                        Document::select('document_number')
                            ->whereColumn('documents.id', 'quality_records.document_id')
                            ->limit(1),
                        'asc'
                    ))
            )
            ->defaultSort('record_date', 'desc')
            ->defaultPaginationPageOption(10)
            ->actions([
                QualityRecordResource::previewAction(),
                QualityRecordResource::downloadAction(),
                Action::make('destroy')
                    ->label('Musnahkan')
                    ->icon('heroicon-m-fire')
                    ->color('danger')
                    ->visible(function (QualityRecord $record, Table $table): bool {
                        $activeTab = $table->getLivewire()->activeTab;
                        $user = auth()->user();

                        return $activeTab === 'kedaluwarsa'
                            && $user
                            && in_array($user->role, ['admin', 'manajemen']);
                    })
                    ->authorize(fn(QualityRecord $record): bool => auth()->user()->can('destroy', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Musnahkan Rekaman Mutu')
                    ->modalDescription('Apakah Anda yakin ingin memusnahkan rekaman mutu ini? Status akan diubah menjadi "Dimusnahkan" tanpa menghapus file.')
                    ->modalSubmitActionLabel('Ya, Musnahkan')
                    ->action(function (QualityRecord $record): void {
                        $record->update(['status' => 'Dimusnahkan']);

                        Notification::make()
                            ->title('Rekaman mutu berhasil dimusnahkan')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('destroy')
                        ->label('Musnahkan')
                        ->icon('heroicon-m-fire')
                        ->color('danger')
                        ->visible(function (Table $table): bool {
                            $activeTab = $table->getLivewire()->activeTab;
                            $user = auth()->user();

                            return $activeTab === 'kedaluwarsa'
                                && $user
                                && in_array($user->role, ['admin', 'manajemen']);
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Musnahkan Rekaman Mutu')
                        ->modalDescription('Apakah Anda yakin ingin memusnahkan rekaman mutu yang dipilih? Status akan diubah menjadi "Dimusnahkan" tanpa menghapus file.')
                        ->modalSubmitActionLabel('Ya, Musnahkan')
                        ->action(function (Collection $records): void {
                            $records->each(function (QualityRecord $record): void {
                                if (auth()->user()->can('destroy', $record)) {
                                    $record->update(['status' => 'Dimusnahkan']);
                                }
                            });

                            Notification::make()
                                ->title('Rekaman mutu berhasil dimusnahkan')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->emptyStateHeading(function (Table $table): string {
                $activeTab = $table->getLivewire()->activeTab;

                return match ($activeTab) {
                    'mendekati' => 'Tidak ada rekaman mendekati kedaluwarsa',
                    'kedaluwarsa' => 'Tidak ada rekaman kedaluwarsa',
                    'dimusnahkan' => 'Tidak ada rekaman yang telah dimusnahkan',
                    default => 'Tidak ada rekaman aktif',
                };
            })
            ->emptyStateDescription(function (Table $table): string {
                $activeTab = $table->getLivewire()->activeTab;

                return match ($activeTab) {
                    'mendekati' => 'Belum ada data rekaman pada kategori Mendekati Kedaluwarsa.',
                    'kedaluwarsa' => 'Belum ada data rekaman pada kategori Kedaluwarsa.',
                    'dimusnahkan' => 'Belum ada data rekaman pada kategori Telah Dimusnahkan.',
                    default => 'Belum ada data rekaman pada kategori Semua Rekaman Aktif.',
                };
            });
    }
}
