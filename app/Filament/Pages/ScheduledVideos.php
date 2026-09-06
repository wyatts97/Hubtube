<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use App\Events\VideoProcessed;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\VideoService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduledVideos extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-clock';
    protected static ?string $navigationLabel = 'Scheduled';
    protected static string | \UnitEnum | null $navigationGroup = 'Content';
    protected static ?int $navigationSort = 6;
    protected string $view = 'filament.pages.scheduled-videos';

    // Scheduled count is surfaced as a topbar pill (see SystemStatusBar::getActionItems).

    protected function getHeaderActions(): array
    {
        return [
            Action::make('configureSchedule')
            ->label('Schedule Settings')
            ->icon('phosphor-gear')
            ->schema([
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

            Action::make('recalculate')
            ->label('Recalculate Times')
            ->icon('phosphor-arrows-clockwise')
            ->color('warning')
            ->action(function () {
            app(VideoService::class)->recalculateScheduleQueue();
            Notification::make()->title('Queue times updated!')->success()->send();
        }),

            Action::make('shuffle')
            ->label('Shuffle Queue')
            ->icon('phosphor-shuffle')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Shuffle the scheduled queue?')
            ->modalDescription('Every pending video is put in a random order and its publish time is reassigned from the current schedule settings. The existing order cannot be recovered.')
            ->modalSubmitActionLabel('Shuffle')
            ->action(function () {
            $count = $this->shuffleQueue();

            if ($count === 0) {
                Notification::make()->title('Nothing to shuffle')->warning()->send();

                return;
            }

            AdminLogger::settingsSaved('Queue Shuffle', ['queue_order', 'scheduled_at']);
            Notification::make()
            ->title("Shuffled {$count} scheduled videos")
            ->body('Publish times were reassigned to match the new order.')
            ->success()
            ->send();
        }),
        ];
    }

    /**
     * Randomise the pending queue, then rebuild the publish times from it.
     *
     * Order and publish time are two different columns: `queue_order` is what
     * the admin table sorts on, but PublishScheduledVideos selects on
     * `scheduled_at`. Renumbering alone would therefore reshuffle this page and
     * change nothing about what actually goes out. recalculateScheduleQueue()
     * is the bridge — it walks videos in `queue_order` and rewrites both the
     * order (closing any gaps) and `scheduled_at` from the configured
     * posts-per-day and start hour. It is the same method the "Recalculate
     * Times" action calls.
     *
     * @return int  number of videos reordered
     */
    protected function shuffleQueue(): int
    {
        $ids = Video::query()
            ->whereNotNull('queue_order')
            ->whereNull('published_at')
            ->pluck('id')
            ->shuffle();

        if ($ids->isEmpty()) {
            return 0;
        }

        // queue_order carries no unique index, so the intermediate states
        // during this loop cannot collide.
        DB::transaction(function () use ($ids) {
            foreach ($ids as $position => $id) {
                Video::whereKey($id)->update(['queue_order' => $position + 1]);
            }
        });

        app(VideoService::class)->recalculateScheduleQueue();

        return $ids->count();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
            Video::query()
            ->with('user', 'category')
            ->whereNotNull('queue_order')
            ->whereNull('published_at')
        )
            ->reorderable('queue_order')
            ->defaultSort('queue_order')
            ->columns([
                ImageColumn::make('thumbnail_display')
                    ->label('Thumbnail')
                    ->getStateUsing(fn (Video $record): ?string => $record->thumbnail_url)
                    ->height(50)
                    ->width(89)
                    ->extraImgAttributes(['class' => 'rounded object-cover'])
                    ->defaultImageUrl(url('/icons/icon-192x192.png')),
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
            ->recordActions([
            Action::make('publishNow')
            ->label('Publish Now')
            ->icon('phosphor-rocket-launch')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (Video $record) {
            $record->update([
                    'is_approved' => true,
                    'published_at' => now(),
                    'scheduled_at' => null,
                    'queue_order' => null,
                    'requires_schedule' => false,
                ]);
            app(VideoService::class)->recalculateScheduleQueue();

            // Fire notification now that video is actually live
            $alreadyNotified = \App\Models\Notification::where('user_id', $record->user_id)
                ->where('type', 'video_processed')
                ->where('data->video_id', $record->id)
                ->exists();
            if (!$alreadyNotified) {
                event(new VideoProcessed($record));
            }

            Notification::make()->title('Video published immediately')->success()->send();
        }),
            Action::make('removeFromQueue')
            ->label('Remove')
            ->icon('phosphor-x-circle')
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