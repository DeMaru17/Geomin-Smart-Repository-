<?php

namespace App\Filament\Resources\QualityRecords;

use App\Filament\Resources\QualityRecords\Pages\CreateQualityRecord;
use App\Filament\Resources\QualityRecords\Pages\EditQualityRecord;
use App\Filament\Resources\QualityRecords\Pages\ListQualityRecords;
use App\Filament\Resources\QualityRecords\Schemas\QualityRecordForm;
use App\Filament\Resources\QualityRecords\Tables\QualityRecordsTable;
use App\Models\QualityRecord;
use App\Services\PdfStamperService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class QualityRecordResource extends Resource
{
    protected static ?string $model = QualityRecord::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Rekaman Mutu';

    protected static ?string $modelLabel = 'Rekaman Mutu';

    protected static ?string $pluralModelLabel = 'Rekaman Mutu';

    protected static ?string $slug = 'quality-records';

    public static function getNavigationBadge(): ?string
    {
        $count = QualityRecord::where('status', 'Aktif')
            ->whereNotNull('expiration_date')
            ->where('expiration_date', '<=', Carbon::today()->addDays(30))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return QualityRecordForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return QualityRecordsTable::table($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQualityRecords::route('/'),
            'create' => CreateQualityRecord::route('/create'),
            'edit' => EditQualityRecord::route('/{record}/edit'),
        ];
    }

    /**
     * Determine if a file path has a previewable extension (PDF or image).
     */
    public static function isPreviewableExtension(?string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        return in_array($extension, ['pdf', 'jpg', 'jpeg', 'png']);
    }

    /**
     * Determine if a file path has a PDF extension.
     */
    public static function isPdfExtension(?string $filePath): bool
    {
        if (empty($filePath)) {
            return false;
        }

        return strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'pdf';
    }

    /**
     * Preview action: opens file in secure viewer (new tab).
     * Uses the quality-record.viewer route for PDF files.
     */
    public static function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Pratinjau')
            ->icon('heroicon-m-eye')
            ->color('info')
            ->visible(fn(QualityRecord $record): bool => self::isPreviewableExtension($record->file_path))
            ->authorize(fn(QualityRecord $record): bool => auth()->user()->can('previewFile', $record))
            ->url(fn(QualityRecord $record): string => route('quality-record.viewer', $record->id))
            ->openUrlInNewTab();
    }

    /**
     * Download action: downloads the file with record_name as filename.
     * For destroyed PDF records, applies the OBSOLETE stamp before download.
     * Non-PDF destroyed records bypass the stamper and download the original file.
     */
    public static function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Unduh')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->visible(fn(QualityRecord $record): bool => !empty($record->file_path))
            ->authorize(fn(QualityRecord $record): bool => auth()->user()->can('downloadFile', $record))
            ->action(function (QualityRecord $record) {
                if (empty($record->file_path) || !Storage::disk('local')->exists($record->file_path)) {
                    Notification::make()
                        ->title('File tidak tersedia')
                        ->danger()
                        ->send();

                    return;
                }

                $extension = pathinfo($record->file_path, PATHINFO_EXTENSION);
                $filename = $record->record_name . '.' . $extension;

                // Destroyed PDF records get the OBSOLETE stamp
                if ($record->status === 'Dimusnahkan' && self::isPdfExtension($record->file_path)) {
                    try {
                        $pdfContent = app(PdfStamperService::class)->stampObsoleteFromPath($record->file_path);

                        return response()->streamDownload(function () use ($pdfContent) {
                            echo $pdfContent;
                        }, $filename, [
                            'Content-Type' => 'application/pdf',
                        ]);
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('File tidak tersedia')
                            ->body('Gagal memproses PDF.')
                            ->danger()
                            ->send();

                        return;
                    }
                }

                // Non-PDF destroyed records or active records: download original file
                $path = Storage::disk('local')->path($record->file_path);

                return response()->download($path, $filename);
            });
    }
}
