<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivityLog extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Logs';
    protected static ?string $navigationGroup = 'Tools';
    protected static ?int $navigationSort = 98;
    protected static string $view = 'filament.pages.activity-log';

    public string $filterLog = 'all';
    public string $filterSearch = '';
    public int $perPage = 50;
    public int $page = 1;

    // Causer name cache (persists across poll refreshes within same request)
    protected array $causerCache = [];

    public function getTitle(): string
    {
        return 'Activity Log';
    }

    public function getSubheading(): ?string
    {
        return 'Real-time log of admin actions, authentication events, and system errors.';
    }

    public function getActivitiesProperty()
    {
        $query = DB::table('activity_log')
            ->orderByDesc('created_at');

        if ($this->filterLog !== 'all') {
            $query->where('log_name', $this->filterLog);
        }

        if (!empty($this->filterSearch)) {
            $search = '%' . $this->filterSearch . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                  ->orWhere('properties', 'like', $search);
            });
        }

        return $query->paginate($this->perPage, ['*'], 'page', $this->page);
    }

    public function getLogCountsProperty(): array
    {
        try {
            $counts = DB::table('activity_log')
                ->select('log_name', DB::raw('count(*) as count'))
                ->groupBy('log_name')
                ->pluck('count', 'log_name')
                ->toArray();

            $counts['all'] = array_sum($counts);

            return $counts;
        } catch (\Throwable) {
            return ['all' => 0];
        }
    }

    public function setFilter(string $log): void
    {
        $this->filterLog = $log;
        $this->page = 1;
    }

    public function updatedFilterSearch(): void
    {
        $this->page = 1;
    }

    public function nextPage(): void
    {
        $this->page++;
    }

    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    public function clearLog(string $logName): void
    {
        if ($logName === 'all') {
            DB::table('activity_log')->truncate();
        } else {
            DB::table('activity_log')->where('log_name', $logName)->delete();
        }

        $this->page = 1;

        Notification::make()
            ->title($logName === 'all' ? 'All logs cleared' : ucfirst($logName) . ' logs cleared')
            ->success()
            ->send();
    }

    public function deleteEntry(int $id): void
    {
        DB::table('activity_log')->where('id', $id)->delete();

        Notification::make()
            ->title('Log entry deleted')
            ->success()
            ->send();
    }

    public function resolveCauserName(?int $causerId, ?string $causerType): string
    {
        if (!$causerId || !$causerType) {
            return 'System';
        }

        $key = $causerType . ':' . $causerId;

        if (!isset($this->causerCache[$key])) {
            if ($causerType === 'App\\Models\\User') {
                $user = DB::table('users')->where('id', $causerId)->select('username')->first();
                $this->causerCache[$key] = $user ? $user->username : "User #{$causerId}";
            } else {
                $this->causerCache[$key] = class_basename($causerType) . " #{$causerId}";
            }
        }

        return $this->causerCache[$key];
    }

    public function resolveSubjectLabel(?int $subjectId, ?string $subjectType): string
    {
        if (!$subjectId || !$subjectType) {
            return '';
        }

        return class_basename($subjectType) . " #{$subjectId}";
    }
}
