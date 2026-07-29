<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Actions\Action;
use Throwable;
use ZipArchive;
use RuntimeException;
use App\Services\DataExportService;
use Filament\Forms\Components\Checkbox;
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

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-tray-arrow-down';
    protected static ?string $navigationLabel = 'Data Export';
    protected static string | \UnitEnum | null $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 99;
    protected string $view = 'filament.pages.data-export';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'export_users' => false,
            'export_videos' => false,
            'export_images' => false,
            'users_format' => 'csv',
            'media_format' => 'zip',
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Select Data to Export')
                    ->description('Choose which data types you want to export from the site.')
                    ->schema([
                        Checkbox::make('export_users')
                            ->label('Export Users')
                            ->live(),
                        Checkbox::make('export_videos')
                            ->label('Export Videos (with media files)')
                            ->live(),
                        Checkbox::make('export_images')
                            ->label('Export Images (with media files)')
                            ->live(),
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
                            ->visible(fn (Get $get): bool => (bool) $get('export_users'))
                            ->required(fn (Get $get): bool => (bool) $get('export_users')),

                        Select::make('media_format')
                            ->label('Media Export Format')
                            ->options([
                                'zip' => 'ZIP Archive',
                            ])
                            ->default('zip')
                            ->visible(fn (Get $get): bool => (bool) ($get('export_videos') || $get('export_images')))
                            ->required(fn (Get $get): bool => (bool) ($get('export_videos') || $get('export_images'))),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Data')
                ->icon('phosphor-tray-arrow-down')
                ->action('export'),
        ];
    }

    public function export(): Response|StreamedResponse
    {
        $exportUsers = (bool) ($this->data['export_users'] ?? false);
        $exportVideos = (bool) ($this->data['export_videos'] ?? false);
        $exportImages = (bool) ($this->data['export_images'] ?? false);
        $usersFormat = $this->data['users_format'] ?? 'csv';

        // Validate at least one export type is selected
        if (!$exportUsers && !$exportVideos && !$exportImages) {
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
            if ($exportUsers) {
                $userFilePath = $service->exportUsers($usersFormat);
                $exportedFiles[] = [
                    'path' => $userFilePath,
                    'name' => "users_export_{$usersFormat}." . $usersFormat,
                ];
            }

            // Export videos if selected
            if ($exportVideos) {
                $videoFilePath = $service->exportVideos();
                $exportedFiles[] = [
                    'path' => $videoFilePath,
                    'name' => "videos_export.zip",
                ];
            }

            // Export images if selected
            if ($exportImages) {
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

        } catch (Throwable $e) {
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

        $zip = new ZipArchive();
        if ($zip->open($tempPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Failed to create master ZIP file");
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
