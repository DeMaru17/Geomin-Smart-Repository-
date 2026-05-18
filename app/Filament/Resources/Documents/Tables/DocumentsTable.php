<?php

namespace App\Filament\Resources\Documents\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([])
            ->description('Repositori dokumen internal dan eksternal')
            ->columns([
                TextColumn::make('document_number')
                    ->label('Nomor Dokumen')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->weight('bold'),
                TextColumn::make('title')
                    ->label('Judul Dokumen')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('department.name')
                    ->label('Divisi'),
                TextColumn::make('is_external')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn(bool $state) => $state ? 'Eksternal' : 'Internal')
                    ->color(fn(bool $state) => $state ? 'warning' : 'success'),
                TextColumn::make('revisions_latest.revision_number')
                    ->label('Rev.')
                    ->default('00'),
                TextColumn::make('revisions_latest.status')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Published', 'Terbit' => 'success',
                        'Draft' => 'gray',
                        'In_Review' => 'warning',
                        'Approved' => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('isoClauses')
                    ->label('Klausul ISO')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->limitList(2)
                    ->formatStateUsing(fn($state) => $state->clause_number . ' - ' . $state->description),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label('Kategori')
                    ->relationship('category', 'name'),
                SelectFilter::make('department_id')
                    ->label('Divisi')
                    ->relationship('department', 'name'),
                TernaryFilter::make('is_external')
                    ->label('Jenis Dokumen')
                    ->placeholder('Semua Jenis')
                    ->trueLabel('Dokumen Eksternal')
                    ->falseLabel('Dokumen Internal'),
                SelectFilter::make('isoClauses')
                    ->label('Klausul ISO')
                    ->multiple()
                    ->relationship('isoClauses', 'clause_number')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record->clause_number . ' - ' . $record->description)
                    ->searchable()
                    ->preload(false),
            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
            ->actions([
                ActionGroup::make([
                    Action::make('pratinjau')
                        ->label('Pratinjau')
                        ->icon('heroicon-m-eye')
                        ->color('info')
                        ->url(function ($record) {
                            $revision = $record->revisions()
                                ->whereIn('status', ['Published', 'Approved'])
                                ->latest()
                                ->first() ?? $record->revisions()->latest()->first();

                            return $revision ? route('secure.viewer', ['id' => $revision->id]) : '#';
                        })
                        ->openUrlInNewTab(),
                    ViewAction::make(),
                    EditAction::make()
                        ->visible(fn() => in_array(auth()->user()?->role, ['admin', 'manajemen'])),
                ])->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi'),
            ]);
    }
}
