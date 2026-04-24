<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-m-queue-list'),

            'errors' => Tab::make('Errors')
                ->icon('heroicon-m-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('log_name', 'error'))
                ->badge(fn () => $this->safeCount(['error']))
                ->badgeColor('danger'),

            'auth' => Tab::make('Auth')
                ->icon('heroicon-m-shield-check')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('log_name', 'auth'))
                ->badge(fn () => $this->safeCount(['auth']))
                ->badgeColor('warning'),

            'admin' => Tab::make('Admin')
                ->icon('heroicon-m-wrench-screwdriver')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('log_name', 'admin'))
                ->badge(fn () => $this->safeCount(['admin']))
                ->badgeColor('info'),

            'system' => Tab::make('System')
                ->icon('heroicon-m-cog-6-tooth')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('log_name', 'system')),
        ];
    }

    public function getTableQuery(): \Illuminate\Database\Eloquent\Builder
    {
        try {
            return parent::getTableQuery();
        } catch (\Throwable) {
            return Activity::query()->whereRaw('0 = 1');
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('openApplicationLogs')
                ->label('Application Logs')
                ->icon('heroicon-o-folder-open')
                ->color('gray')
                ->url(ActivityLogResource::getUrl('application')),
        ];
    }

    private function safeCount(array $logNames): ?string
    {
        try {
            $c = Activity::query()->whereIn('log_name', $logNames)->count();
            return $c > 0 ? (string) $c : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
