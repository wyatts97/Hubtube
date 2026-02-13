<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

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

    public function getActivitiesProperty()
    {
        $query = DB::table('activity_log')
            ->orderByDesc('created_at');

        // Filter by log name
        if ($this->filterLog !== 'all') {
            $query->where('log_name', $this->filterLog);
        }

        // Search filter
        if (!empty($this->filterSearch)) {
            $search = '%' . $this->filterSearch . '%';
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', $search)
                  ->orWhere('properties', 'like', $search);
            });
        }

        return $query->paginate($this->perPage);
    }

    public function getLogCountsProperty(): array
    {
        $counts = DB::table('activity_log')
            ->select('log_name', DB::raw('count(*) as count'))
            ->groupBy('log_name')
            ->pluck('count', 'log_name')
            ->toArray();

        $counts['all'] = array_sum($counts);

        return $counts;
    }

    public function setFilter(string $log): void
    {
        $this->filterLog = $log;
        $this->resetPage();
    }

    public function resetPage(): void
    {
        // Reset pagination when filters change
    }

    public function updatedFilterSearch(): void
    {
        $this->resetPage();
    }

    public function clearLog(string $logName): void
    {
        if ($logName === 'all') {
            DB::table('activity_log')->truncate();
        } else {
            DB::table('activity_log')->where('log_name', $logName)->delete();
        }

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

    /**
     * Resolve causer name from the causer_id + causer_type stored in the activity log.
     */
    public function getCauserName(?int $causerId, ?string $causerType): string
    {
        if (!$causerId || !$causerType) {
            return 'System';
        }

        static $cache = [];
        $key = $causerType . ':' . $causerId;

        if (!isset($cache[$key])) {
            if ($causerType === 'App\\Models\\User') {
                $user = DB::table('users')->where('id', $causerId)->select('username')->first();
                $cache[$key] = $user ? $user->username : "User #{$causerId}";
            } else {
                $cache[$key] = class_basename($causerType) . " #{$causerId}";
            }
        }

        return $cache[$key];
    }

    /**
     * Resolve subject name from the subject_id + subject_type stored in the activity log.
     */
    public function getSubjectLabel(?int $subjectId, ?string $subjectType): string
    {
        if (!$subjectId || !$subjectType) {
            return '';
        }

        $type = class_basename($subjectType);

        return "{$type} #{$subjectId}";
    }
}
