<?php

namespace App\Filament\Resources\Documents\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                Textarea::make('change_summary')
                    ->label('Ringkasan Perubahan')
                    ->columnSpanFull(),
                Hidden::make('uploader_id')
                    ->default(auth()->id()),
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

                TextColumn::make('file_path')
                    ->label('Pratinjau')
                    ->formatStateUsing(fn () => 'Buka Pratinjau')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->weight('bold')
                    ->action(
                        Action::make('view_pdf')
                            ->modalHeading('Pratinjau Dokumen Mutu')
                            ->modalContent(fn ($record) => view('filament.components.pdf-viewer', [
                                'url' => route('pdf.view', ['id' => $record->id])
                            ]))
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Tutup')
                            ->modalWidth('7xl') // Atau gunakan 'screen' jika ingin maksimal
                    ),

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
                ViewAction::make(),
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
