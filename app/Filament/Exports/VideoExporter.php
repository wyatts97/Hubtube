<?php

namespace App\Filament\Exports;

use App\Models\Video;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VideoExporter extends Exporter
{
    protected static ?string $model = Video::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id'),
            ExportColumn::make('title'),
            ExportColumn::make('slug'),
            ExportColumn::make('user.username')->label('Uploader'),
            ExportColumn::make('category.name')->label('Category'),
            ExportColumn::make('status'),
            ExportColumn::make('is_approved')->label('Approved'),
            ExportColumn::make('is_featured')->label('Featured'),
            ExportColumn::make('views_count')->label('Views'),
            ExportColumn::make('likes_count')->label('Likes'),
            ExportColumn::make('duration')->label('Duration (s)'),
            ExportColumn::make('price'),
            ExportColumn::make('rent_price'),
            ExportColumn::make('created_at')->label('Uploaded At'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your video export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
