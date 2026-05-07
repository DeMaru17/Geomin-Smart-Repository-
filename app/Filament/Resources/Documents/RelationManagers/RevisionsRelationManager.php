<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('revision_number')
                    ->label('Nomor Revisi')
                    ->default(function ($livewire) {
                        $document = $livewire->getOwnerRecord();
                        $latestRevision = $document->revisions()->latest('id')->first();

                        if (! $latestRevision) {
                            return '00';
                        }

                        $nextRevision = intval($latestRevision->revision_number) + 1;

                        return str_pad($nextRevision, 2, '0', STR_PAD_LEFT);
                    })
                    ->dehydrated()
                    ->required(),
                FileUpload::make('file_path')
                    ->label('Dokumen Fisik (PDF)')
                    ->directory('geomin-documents')
                    ->acceptedFileTypes(['application/pdf'])
                    ->required(),
                Select::make('status')
                    ->label('Status Dokumen')
                    ->options([
                        'Draft' => 'Draft',
                        'In_Review' => 'In Review',
                        'Approved' => 'Approved',
                        'Published' => 'Published',
                        'Obsolete' => 'Obsolete (AVOID)',
                    ])
                    ->default('Draft')
                    ->required(),
                FileUpload::make('word_file_path')
                    ->label('Dokumen Master (Word)')
                    ->directory('geomin-documents/word')
                    ->acceptedFileTypes(['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'])
                    ->helperText('Hanya dapat diunduh oleh Admin/Manajemen.'),
                Textarea::make('change_summary')
                    ->label('Ringkasan Perubahan')
                    ->columnSpanFull(),
                Hidden::make('uploader_id')
                    ->default(auth()->user()?->id),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('revision_number')
            ->columns([
                TextColumn::make('revision_number')
                    ->label('Rev')
                    ->sortable(),

                TextColumn::make('change_summary')
                    ->label('Ringkasan Perubahan')
                    ->limit(40) // Membatasi karakter agar tabel tidak memanjang ke bawah
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return $state; // Menampilkan teks lengkap saat disorot mouse
                    })
                    ->placeholder('Tidak ada catatan perubahan') // Teks default jika kosong
                    ->color('gray'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Draft' => 'gray',
                        'In_Review' => 'warning',
                        'Approved' => 'success',
                        'Published' => 'info',
                        'Obsolete' => 'danger',
                        default => 'primary',
                    }),

                TextColumn::make('uploader.name')
                    ->label('Diunggah Oleh'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                Action::make('pratinjau')
                    ->label('Pratinjau')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->url(fn ($record) => route('secure.viewer', ['id' => $record->id])),
                Action::make('download_pdf')
                    ->label('Unduh PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('danger')
                    ->action(function ($record) {
                        $fileName = "{$record->document->document_number} - {$record->document->title} (Rev {$record->revision_number}).pdf";

                        return response()->download(Storage::path($record->file_path), $fileName);
                    }),
                Action::make('download_word')
                    ->label('Unduh Word')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->visible(fn ($record) => in_array(auth()->user()?->role, ['admin', 'manajemen']) && $record->word_file_path)
                    ->action(function ($record) {
                        $extension = pathinfo($record->word_file_path, PATHINFO_EXTENSION);
                        $fileName = "{$record->document->document_number} - {$record->document->title} (Rev {$record->revision_number}).{$extension}";

                        return response()->download(Storage::path($record->word_file_path), $fileName);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
