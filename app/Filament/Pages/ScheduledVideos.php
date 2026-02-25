<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasCustomizableNavigation;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\VideoService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

class ScheduledVideos extends Page implements HasTable
{
    use HasCustomizableNavigation;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Scheduled Queue';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.pages.scheduled-videos';

    public static function getNavigationBadge(): ?string
    {
        $count = Video::whereNotNull('queue_order')
            ->where('is_approved', false)
            ->count();

        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('configureSchedule')
            ->label('Schedule Settings')
            ->icon('heroicon-o-cog-6-tooth')
            ->form([
                Select::make('posts_per_day')
                ->label('Posts Per Day')
                ->options([
                    1 => '1 Post per Day',
                    2 => '2 Posts per Day (Every 12h)',
                    3 => '3 Posts per Day (Every 8h)',
                    4 => '4 Posts per Day (Every 6h)',
                    6 => '6 Posts per Day (Every 4h)',
                ])
                ->default((int)Setting::get('schedule_posts_per_day', 1))
                ->required(),
                TimePicker::make('start_hour')
                ->label('Daily Start Time')
                ->seconds(false)
                ->default(Setting::get('schedule_start_hour', '08:00:00'))
                ->required(),
            ])
            ->action(function (array $data) {
            Setting::set('schedule_posts_per_day', $data['posts_per_day']);
            Setting::set('schedule_start_hour', $data['start_hour']);
            AdminLogger::settingsSaved('Queue Configuration', array_keys($data));
            Notification::make()->title('Schedule Settings Updated')->success()->send();
            app(VideoService::class)->recalculateScheduleQueue();
        }),

            \Filament\Actions\Action::make('recalculate')
            ->label('Recalculate Times')
            ->icon('heroicon-o-arrow-path')
            ->color('warning')
            ->action(function () {
            app(VideoService::class)->recalculateScheduleQueue();
            Notification::make()->title('Queue times updated!')->success()->send();
        }),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
            Video::query()
            ->with('user', 'category')
            ->whereNotNull('queue_order')
            ->where('is_approved', false)
        )
            ->reorderable('queue_order')
            ->defaultSort('queue_order')
            ->columns([
            TextColumn::make('title')
            ->searchable()
            ->sortable()
            ->weight('bold')
            ->limit(50)
            ->description(fn(Video $record): string => $record->formatted_duration ?: '—'),
            TextColumn::make('user.username')
            ->label('Uploader')
            ->size('sm'),
            TextColumn::make('scheduled_at')
            ->label('Scheduled For')
            ->dateTime('M j, Y g:i A')
            ->sortable()
            ->description(fn(Video $record) => $record->scheduled_at ? $record->scheduled_at->diffForHumans() : ''),
            TextColumn::make('status')
            ->badge()
            ->color(fn(string $state) => $state === 'processed' ? 'success' : 'warning')
            ->formatStateUsing(fn(string $state) => $state === 'processed' ? 'Ready' : ucfirst($state)),
        ])
            ->actions([
            Action::make('publishNow')
            ->label('Publish Now')
            ->icon('heroicon-o-rocket-launch')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Video $record) {
            $record->update([
                    'is_approved' => true,
                    'published_at' => now(),
                    'scheduled_at' => null,
                    'queue_order' => null,
                ]);
            app(VideoService::class)->recalculateScheduleQueue();
            Notification::make()->title('Video published immediately')->success()->send();
        }),
            Action::make('removeFromQueue')
            ->label('Remove')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->action(function (Video $record) {
            $record->update([
                    'scheduled_at' => null,
                    'queue_order' => null,
                ]);
            app(VideoService::class)->recalculateScheduleQueue();
            Notification::make()->title('Video removed from queue')->success()->send();
        }),
        ]);
    }
}
