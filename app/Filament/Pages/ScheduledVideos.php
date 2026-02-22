<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasCustomizableNavigation;
use App\Models\ScheduleTemplate;
use App\Models\Video;
use App\Services\AdminLogger;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class ScheduledVideos extends Page implements HasForms
{
    use HasCustomizableNavigation;
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationLabel = 'Scheduled';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 6;
    protected static string $view = 'filament.pages.scheduled-videos';

    public ?array $templateData = [];
    public bool $showTemplateForm = false;
    public ?int $editingTemplateId = null;

    public static function getNavigationBadge(): ?string
    {
        return Video::whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    protected function getForms(): array
    {
        return [
            'templateForm',
        ];
    }

    public function mount(): void
    {
        $this->templateForm->fill([]);
    }

    public function templateForm(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Template Name')
                    ->required()
                    ->placeholder('e.g. Weekday Evenings'),
                Repeater::make('slots')
                    ->label('Time Slots')
                    ->schema([
                        Select::make('day')
                            ->options([
                                'monday' => 'Monday',
                                'tuesday' => 'Tuesday',
                                'wednesday' => 'Wednesday',
                                'thursday' => 'Thursday',
                                'friday' => 'Friday',
                                'saturday' => 'Saturday',
                                'sunday' => 'Sunday',
                            ])
                            ->required(),
                        TimePicker::make('time')
                            ->seconds(false)
                            ->required(),
                    ])
                    ->columns(2)
                    ->minItems(1)
                    ->defaultItems(1)
                    ->addActionLabel('Add Time Slot')
                    ->collapsible(),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->statePath('templateData');
    }

    public function getScheduledVideosProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Video::with('user', 'category')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->get();
    }

    public function getPublishedScheduledProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return Video::with('user')
            ->whereNotNull('published_at')
            ->where('published_at', '>', now()->subDays(7))
            ->where('is_approved', true)
            ->orderByDesc('published_at')
            ->limit(10)
            ->get();
    }

    public function getTemplatesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        return ScheduleTemplate::orderBy('name')->get();
    }

    public function openTemplateForm(?int $id = null): void
    {
        $this->editingTemplateId = $id;
        $this->showTemplateForm = true;

        if ($id) {
            $template = ScheduleTemplate::find($id);
            if ($template) {
                $this->templateForm->fill([
                    'name' => $template->name,
                    'slots' => $template->slots,
                    'is_active' => $template->is_active,
                ]);
            }
        } else {
            $this->templateForm->fill([
                'name' => '',
                'slots' => [['day' => 'monday', 'time' => '18:00']],
                'is_active' => true,
            ]);
        }
    }

    public function closeTemplateForm(): void
    {
        $this->showTemplateForm = false;
        $this->editingTemplateId = null;
        $this->templateForm->fill([]);
    }

    public function saveTemplate(): void
    {
        $data = $this->templateForm->getState();

        if ($this->editingTemplateId) {
            $template = ScheduleTemplate::find($this->editingTemplateId);
            $template?->update($data);
            Notification::make()->title('Template updated')->success()->send();
        } else {
            ScheduleTemplate::create($data);
            Notification::make()->title('Template created')->success()->send();
        }

        AdminLogger::settingsSaved('Schedule Template', array_keys($data));
        $this->closeTemplateForm();
    }

    public function deleteTemplate(int $id): void
    {
        ScheduleTemplate::find($id)?->delete();
        Notification::make()->title('Template deleted')->success()->send();
    }

    public function applyTemplate(int $templateId): void
    {
        $template = ScheduleTemplate::find($templateId);
        if (!$template) {
            Notification::make()->title('Template not found')->danger()->send();
            return;
        }

        // Get unscheduled processed videos that are not yet approved (waiting to be scheduled)
        $videos = Video::where('status', 'processed')
            ->where('is_approved', false)
            ->whereNull('scheduled_at')
            ->orderBy('created_at')
            ->get();

        if ($videos->isEmpty()) {
            Notification::make()->title('No unscheduled videos available')->warning()->send();
            return;
        }

        $slots = $template->getNextSlots($videos->count());

        if (empty($slots)) {
            Notification::make()->title('No available time slots found')->warning()->send();
            return;
        }

        $scheduled = 0;
        foreach ($videos as $index => $video) {
            if (!isset($slots[$index])) break;

            $video->update(['scheduled_at' => $slots[$index]]);
            $scheduled++;
        }

        Notification::make()
            ->title("Scheduled {$scheduled} video(s) using \"{$template->name}\"")
            ->success()
            ->send();
    }

    public function shuffleScheduled(): void
    {
        $videos = Video::whereNotNull('scheduled_at')
            ->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at')
            ->get();

        if ($videos->count() < 2) {
            Notification::make()->title('Need at least 2 scheduled videos to shuffle')->warning()->send();
            return;
        }

        // Collect the existing scheduled times, shuffle the videos, reassign
        $times = $videos->pluck('scheduled_at')->toArray();
        $videoIds = $videos->pluck('id')->toArray();
        shuffle($videoIds);

        foreach ($videoIds as $index => $videoId) {
            Video::where('id', $videoId)->update(['scheduled_at' => $times[$index]]);
        }

        Notification::make()
            ->title('Shuffled ' . count($videoIds) . ' scheduled videos')
            ->success()
            ->send();
    }

    public function unscheduleVideo(int $videoId): void
    {
        $video = Video::find($videoId);
        if ($video) {
            $video->update(['scheduled_at' => null]);
            Notification::make()->title('Video unscheduled')->success()->send();
        }
    }

    public function publishNow(int $videoId): void
    {
        $video = Video::find($videoId);
        if ($video) {
            $video->update([
                'is_approved' => true,
                'published_at' => now(),
                'scheduled_at' => null,
            ]);
            Notification::make()->title('Video published immediately')->success()->send();
        }
    }

    public function reschedule(int $videoId, string $datetime): void
    {
        $video = Video::find($videoId);
        if ($video && $datetime) {
            $video->update(['scheduled_at' => Carbon::parse($datetime)]);
            Notification::make()->title('Video rescheduled')->success()->send();
        }
    }
}
