<?php

namespace App\Filament\Pages;

use App\Services\DataExportService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExport extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';
    protected static ?string $navigationLabel = 'Data Export';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 99;
    protected static string $view = 'filament.pages.data-export';

    public ?array $data = [];

    // Form fields
    public bool $export_users = false;
    public bool $export_videos = false;
    public bool $export_images = false;
    public ?string $users_format = 'csv';
    public ?string $media_format = 'zip';

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function getFormSchema(): array
    {
        return [
            Section::make('Select Data to Export')
                ->description('Choose which data types you want to export from the site.')
                ->schema([
                    Checkbox::make('export_users')
                        ->label('Export Users')
                        ->reactive(),
                    Checkbox::make('export_videos')
                        ->label('Export Videos (with media files)')
                        ->reactive(),
                    Checkbox::make('export_images')
                        ->label('Export Images (with media files)')
                        ->reactive(),
                ]),

            Section::make('Export Format')
                ->description('Choose the format for your export.')
                ->schema([
                    Select::make('users_format')
                        ->label('Users Export Format')
                        ->options([
                            'csv' => 'CSV',
                            'json' => 'JSON',
                            'sql' => 'SQL',
                        ])
                        ->default('csv')
                        ->visible(fn () => $this->export_users)
                        ->required(fn () => $this->export_users),

                    Select::make('media_format')
                        ->label('Media Export Format')
                        ->options([
                            'zip' => 'ZIP Archive',
                        ])
                        ->default('zip')
                        ->visible(fn () => $this->export_videos || $this->export_images)
                        ->required(fn () => $this->export_videos || $this->export_images),
                ]),
        ];
    }

    public function export(): Response|StreamedResponse
    {
        // Validate at least one export type is selected
        if (!$this->export_users && !$this->export_videos && !$this->export_images) {
            Notification::make()
                ->title('No data selected')
                ->body('Please select at least one data type to export.')
                ->warning()
                ->send();
            return response()->noContent();
        }

        $service = app(DataExportService::class);
        $exportedFiles = [];

        try {
            // Clean up old exports first
            $service->cleanupOldExports();

            // Export users if selected
            if ($this->export_users) {
                $userFilePath = $service->exportUsers($this->users_format);
                $exportedFiles[] = [
                    'path' => $userFilePath,
                    'name' => "users_export_{$this->users_format}." . $this->users_format,
                ];
            }

            // Export videos if selected
            if ($this->export_videos) {
                $videoFilePath = $service->exportVideos();
                $exportedFiles[] = [
                    'path' => $videoFilePath,
                    'name' => "videos_export.zip",
                ];
            }

            // Export images if selected
            if ($this->export_images) {
                $imageFilePath = $service->exportImages();
                $exportedFiles[] = [
                    'path' => $imageFilePath,
                    'name' => "images_export.zip",
                ];
            }

            // If only one file, download directly
            if (count($exportedFiles) === 1) {
                return $service->downloadFile($exportedFiles[0]['path'], $exportedFiles[0]['name']);
            }

            // If multiple files, create a master ZIP
            return $this->createMasterZip($exportedFiles);

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Export failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
            return response()->noContent();
        }
    }

    private function createMasterZip(array $files): StreamedResponse
    {
        $filename = "hubtube_export_" . now()->format('Y-m-d_H-i-s') . '.zip';
        $tempPath = Storage::disk('local')->path('exports/' . $filename);

        $zip = new \ZipArchive();
        if ($zip->open($tempPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create master ZIP file");
        }

        foreach ($files as $file) {
            $fullPath = Storage::disk('local')->path($file['path']);
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $file['name']);
            }
        }

        $zip->close();

        return response()->streamDownload(function () use ($tempPath) {
            readfile($tempPath);
            // Cleanup master ZIP after sending
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }, $filename);
    }
}
