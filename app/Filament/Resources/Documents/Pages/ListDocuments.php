<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\Exports\ExternalDocumentExport;
use App\Services\InternalDocumentExportService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use pxlrbt\FilamentExcel\Actions\ExportAction;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Dokumen'),
            ActionGroup::make([
                Action::make('export_form_202')
                    ->label('273.106.202 (Internal)')
                    ->color('success')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->action(function (InternalDocumentExportService $service) {
                        $response = $service->export();

                        if ($response === false) {
                            $templatePath = $service->getTemplatePath();

                            if (! file_exists($templatePath)) {
                                Notification::make()
                                    ->title('File template tidak ditemukan')
                                    ->body('File template Excel Form 273.106.202 tidak tersedia di storage.')
                                    ->danger()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->title('Tidak ada data')
                                    ->body('Tidak ada dokumen internal aktif yang memenuhi syarat untuk diekspor.')
                                    ->warning()
                                    ->send();
                            }

                            return null;
                        }

                        return $response;
                    }),
                ExportAction::make('export_form_203')
                    ->label('273.106.203 (Eksternal)')
                    ->color('warning')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->exports([
                        ExternalDocumentExport::make(),
                    ]),
            ])
                ->label('Cetak Daftar Induk')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->button(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'internal' => Tab::make('Internal')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_external', false))
                ->icon('heroicon-m-document-text'),
            'eksternal' => Tab::make('Eksternal')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_external', true))
                ->icon('heroicon-m-globe-alt'),
            'semua' => Tab::make('Semua Dokumen'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'internal';
    }
}
