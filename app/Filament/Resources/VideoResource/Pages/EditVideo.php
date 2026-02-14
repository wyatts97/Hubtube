<?php

namespace App\Filament\Resources\VideoResource\Pages;

use App\Filament\Resources\VideoResource;
use App\Jobs\ProcessVideoJob;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVideo extends EditRecord
{
    protected static string $resource = VideoResource::class;

    protected static string $view = 'filament.resources.video-resource.pages.edit-video';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_frontend')
                ->label('View on Site')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => '/' . $this->record->slug)
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->status === 'processed' && $this->record->is_approved),

            Actions\Action::make('approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->action(fn () => $this->record->update([
                    'is_approved' => true,
                    'published_at' => $this->record->published_at ?? now(),
                ]))
                ->visible(fn () => !$this->record->is_approved && $this->record->status === 'processed'),

            Actions\Action::make('reprocess')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('This will re-dispatch the video processing job. Existing transcoded files will be skipped.')
                ->action(function () {
                    $this->record->update(['status' => 'pending']);
                    ProcessVideoJob::dispatch($this->record)->onQueue('video-processing');
                })
                ->visible(fn () => in_array($this->record->status, ['failed', 'processing'])),

            Actions\DeleteAction::make(),
        ];
    }
}
