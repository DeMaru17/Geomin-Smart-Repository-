<?php

namespace App\Filament\Resources\DistributionLogs;

use App\Filament\Resources\DistributionLogs\Pages\ListDistributionLogs;
use App\Models\DocumentDistributionLog;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DistributionLogResource extends Resource
{
    protected static ?string $model = DocumentDistributionLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Manajemen Dokumen';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Log Distribusi';

    protected static ?string $pluralModelLabel = 'Log Distribusi Dokumen';

    protected static ?string $slug = 'distribution-logs';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('accessed_at', 'desc')
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('accessed_at')
                    ->label('Waktu Akses')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                TextColumn::make('documentRevision.document')
                    ->label('Dokumen')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '-';
                        }

                        return $state->document_number . ' - ' . $state->title;
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('documentRevision.document', function (Builder $query) use ($search) {
                            $query->where('document_number', 'like', "%{$search}%")
                                ->orWhere('title', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('documentRevision.revision_number')
                    ->label('Revisi')
                    ->formatStateUsing(fn($state) => str_pad($state ?? '0', 2, '0', STR_PAD_LEFT)),

                TextColumn::make('recipient_name')
                    ->label('Penerima')
                    ->searchable(),

                TextColumn::make('user.name')
                    ->label('Issuer/Sistem')
                    ->default('Guest'),

                TextColumn::make('action')
                    ->label('Aksi')
                    ->searchable()
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Akses Digital (QR Instrumen/Alat)' => 'info',
                        'Salinan Terkendali (Cetak)' => 'success',
                        'Salinan Tidak Terkendali (Unduh)' => 'danger',
                        'Verifikasi Fisik (QR)' => 'gray',
                        default => 'primary',
                    }),
            ])
            ->filters([
                Filter::make('accessed_at')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('accessed_at', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('accessed_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('Dari ' . \Carbon\Carbon::parse($data['from'])->translatedFormat('d M Y'))
                                ->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Sampai ' . \Carbon\Carbon::parse($data['until'])->translatedFormat('d M Y'))
                                ->removeField('until');
                        }

                        return $indicators;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistributionLogs::route('/'),
        ];
    }
}
