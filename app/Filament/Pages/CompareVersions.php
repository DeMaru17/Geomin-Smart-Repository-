<?php

namespace App\Filament\Pages;

use App\Models\Document;
use App\Models\DocumentRevision;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Jfcherng\Diff\DiffHelper;

class CompareVersions extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Perbandingan Versi';

    protected static ?string $title = 'Perbandingan Versi';

    protected static ?string $slug = 'compare-versions';

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.compare-versions';

    public ?array $data = [];

    public ?string $diffHtml = null;

    public int $addedCount = 0;

    public int $removedCount = 0;

    public int $unchangedCount = 0;

    public function mount(): void
    {
        $this->form->fill([
            'document_id' => request()->query('document_id'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make()
                    ->extraAttributes(['class' => 'items-end'])
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('document_id')
                                ->label('Pilih Dokumen')
                                ->options(Document::pluck('title', 'id'))
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function (Set $set) {
                                    $set('old_revision_id', null);
                                    $set('new_revision_id', null);
                                }),

                            Select::make('old_revision_id')
                                ->label('Versi Lama (Kiri)')
                                ->options(fn (Get $get) =>
                                    DocumentRevision::query()
                                        ->where('document_id', $get('document_id'))
                                        ->pluck('revision_number', 'id')
                                        ->map(fn ($val, $key) => "Rev. $val")
                                ),

                            Select::make('new_revision_id')
                                ->label('Versi Baru (Kanan)')
                                ->options(fn (Get $get) => DocumentRevision::query()
                                    ->where('document_id', $get('document_id'))
                                    ->pluck('revision_number', 'id')
                                    ->map(fn ($val, $key) => "Rev. $val")
                                ),

                            Actions::make([
                                Action::make('compare')
                                    ->label('Bandingkan')
                                    ->button()
                                    ->color('primary')
                                    ->icon('heroicon-m-arrow-path-rounded-square')
                                    ->action('generateDiff'),
                            ])
                            ->extraAttributes(['class' => 'justify-center w-full mt-4']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function generateDiff(): void
    {
        $oldId = $this->data['old_revision_id'];
        $newId = $this->data['new_revision_id'];

        if (! $oldId || ! $newId) {
            return;
        }

        // Menggunakan query()->find() untuk mengatasi error Intelephense
        $oldRev = DocumentRevision::query()->find($oldId);
        $newRev = DocumentRevision::query()->find($newId);

        $oldText = $oldRev->extracted_text ?? '';
        $newText = $newRev->extracted_text ?? '';

        $rendererOptions = [
            'detailLevel' => 'word',
            'language' => 'eng',
            'lineNumbers' => true,
            'showHeader' => false,
            'spacesToNbsp' => false,
        ];

        $this->diffHtml = DiffHelper::calculate($oldText, $newText, 'SideBySide', [], $rendererOptions);

        $this->addedCount = substr_count($this->diffHtml, 'diff-added');
        $this->removedCount = substr_count($this->diffHtml, 'diff-deleted');
    }
}
