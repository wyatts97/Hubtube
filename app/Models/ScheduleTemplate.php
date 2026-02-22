<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleTemplate extends Model
{
    protected $fillable = [
        'name',
        'slots',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'slots' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the next N available slot datetimes from this template,
     * starting from $after (defaults to now).
     */
    public function getNextSlots(int $count, ?\Carbon\Carbon $after = null): array
    {
        $after = $after ?? now();
        $slots = $this->slots ?? [];

        if (empty($slots)) {
            return [];
        }

        $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];

        $results = [];
        $cursor = $after->copy()->startOfMinute();

        // Look ahead up to 8 weeks to find enough slots
        $maxDate = $cursor->copy()->addWeeks(8);

        while (count($results) < $count && $cursor->lt($maxDate)) {
            foreach ($slots as $slot) {
                $dayNum = $dayMap[strtolower($slot['day'])] ?? null;
                if ($dayNum === null) continue;

                $time = explode(':', $slot['time'] ?? '12:00');
                $hour = (int) ($time[0] ?? 12);
                $minute = (int) ($time[1] ?? 0);

                // Find next occurrence of this day
                $candidate = $cursor->copy()->next((int) $dayNum);
                $candidate->setTime($hour, $minute, 0);

                // If the day is today and time hasn't passed yet
                if ($cursor->dayOfWeek === $dayNum) {
                    $todayCandidate = $cursor->copy()->setTime($hour, $minute, 0);
                    if ($todayCandidate->gt($after)) {
                        $candidate = $todayCandidate;
                    }
                }

                if ($candidate->gt($after) && $candidate->lt($maxDate)) {
                    $results[] = $candidate;
                }
            }

            $cursor->addWeek();
        }

        // Sort by datetime and take the requested count
        usort($results, fn ($a, $b) => $a->timestamp - $b->timestamp);

        // Remove duplicates (same timestamp)
        $unique = [];
        $seen = [];
        foreach ($results as $dt) {
            $key = $dt->format('Y-m-d H:i');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $dt;
            }
        }

        return array_slice($unique, 0, $count);
    }
}
