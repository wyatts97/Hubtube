<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use App\Filament\Resources\SponsoredCardResource;
use App\Models\SponsoredCard;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class ListSponsoredCards extends ListRecords
{
    protected static string $resource = SponsoredCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_csv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    Forms\Components\FileUpload::make('csv_file')
                        ->label('CSV File')
                        ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                        ->required()
                        ->disk('local')
                        ->directory('temp-imports')
                        ->visibility('private'),
                    Forms\Components\TextInput::make('ribbon_text')
                        ->label('Ribbon Text for All')
                        ->placeholder('e.g. "Clip", "Video", "Product"')
                        ->helperText('Text shown after "Featured" on the ribbon'),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Set All as Active')
                        ->default(true),
                    Forms\Components\Select::make('target_pages')
                        ->label('Target Pages')
                        ->multiple()
                        ->options([
                            'home' => 'Home',
                            'trending' => 'Trending',
                            'search' => 'Search Results',
                            'category' => 'Category Pages',
                            'browse' => 'Browse Videos',
                        ])
                        ->helperText('Leave empty for all pages'),
                ])
                ->action(function (array $data): void {
                    $this->importCsv($data);
                }),
            Actions\CreateAction::make(),
        ];
    }

    protected function importCsv(array $data): void
    {
        $filePath = Storage::disk('local')->path($data['csv_file']);
        
        if (!file_exists($filePath)) {
            Notification::make()
                ->title('Import Failed')
                ->body('Could not find the uploaded file.')
                ->danger()
                ->send();
            return;
        }

        try {
            $csv = Reader::createFromPath($filePath, 'r');
            $csv->setHeaderOffset(0);
            
            $records = $csv->getRecords();
            $imported = 0;
            $skipped = 0;

            foreach ($records as $record) {
                // Skip if no title or URL
                if (empty($record['title']) || empty($record['video URL'])) {
                    $skipped++;
                    continue;
                }

                // Check for duplicate by external_id
                $externalId = $record['unique Item ID'] ?? null;
                if ($externalId && SponsoredCard::where('external_id', $externalId)->exists()) {
                    $skipped++;
                    continue;
                }

                // Parse preview thumbnail URLs - they might be comma-separated or pipe-separated
                $previewImages = [];
                $previewThumbsRaw = $record['preview thumbnail URLs'] ?? '';
                if ($previewThumbsRaw) {
                    // Split by comma or pipe
                    $thumbs = preg_split('/[,|]/', $previewThumbsRaw);
                    foreach ($thumbs as $thumb) {
                        $thumb = trim($thumb);
                        if ($thumb && filter_var($thumb, FILTER_VALIDATE_URL)) {
                            $previewImages[] = $thumb;
                        }
                    }
                }

                // If no preview images, use the default thumbnail
                $thumbnailUrl = $record['default thumbnail URL'] ?? ($record['preview image URL'] ?? '');
                if (empty($previewImages) && $thumbnailUrl) {
                    $previewImages[] = $thumbnailUrl;
                }

                SponsoredCard::create([
                    'external_id' => $externalId,
                    'title' => $record['title'],
                    'thumbnail_url' => $thumbnailUrl,
                    'click_url' => $record['video URL'],
                    'description' => $record['description'] ?? null,
                    'price' => !empty($record['price']) ? (float) $record['price'] : null,
                    'sale_price' => null,
                    'ribbon_text' => $data['ribbon_text'] ?? null,
                    'preview_images' => $previewImages,
                    'studio' => $record['studio'] ?? null,
                    'duration' => !empty($record['duration']) ? (int) $record['duration'] : null,
                    'target_pages' => !empty($data['target_pages']) ? $data['target_pages'] : null,
                    'frequency' => 8,
                    'weight' => 1,
                    'is_active' => $data['is_active'] ?? true,
                    'category_ids' => null,
                    'target_roles' => null,
                ]);

                $imported++;
            }

            // Clean up temp file
            Storage::disk('local')->delete($data['csv_file']);

            Notification::make()
                ->title('Import Complete')
                ->body("Imported {$imported} sponsored cards. Skipped {$skipped} (duplicates or invalid).")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Import Failed')
                ->body('Error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }
}
