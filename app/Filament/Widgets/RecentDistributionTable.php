<?php

namespace App\Filament\Widgets;

use App\Models\DocumentDistributionLog;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class RecentDistributionTable extends TableWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return in_array(auth()->user()?->role, ['admin', 'manajemen']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                DocumentDistributionLog::query()
                    ->with(['documentRevision.document'])
                    ->latest('accessed_at')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('documentRevision.document.title')
                    ->label('Judul Dokumen')
                    ->limit(40),

                TextColumn::make('documentRevision.revision_number')
                    ->label('Revisi')
                    ->formatStateUsing(fn($state) => str_pad($state ?? '0', 2, '0', STR_PAD_LEFT)),

                TextColumn::make('recipient_name')
                    ->label('Penerima'),

                TextColumn::make('purpose')
                    ->label('Tujuan')
                    ->limit(30),

                TextColumn::make('accessed_at')
                    ->label('Waktu Akses')
                    ->dateTime('d-m-Y H:i')
                    ->sortable(),

                IconColumn::make('is_qr_access')
                    ->label('QR')
                    ->boolean()
                    ->trueIcon('heroicon-o-qr-code')
                    ->falseIcon('')
                    ->trueColor('info'),
            ])
            ->defaultSort('accessed_at', 'desc')
            ->paginated(false)
            ->heading('Aktivitas Distribusi Terkini');
    }
}
