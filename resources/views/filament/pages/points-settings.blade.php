<x-filament-panels::page>
    <style>
        .ht-points-stats {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: 1.5rem;
        }
        @media (min-width: 640px) {
            .ht-points-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        }
        .ht-points-stat {
            background-color: #18181b;
            border: 1px solid #27272a;
            border-radius: 0.75rem;
            padding: 1rem;
        }
        .ht-points-stat-label {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-bottom: 0.25rem;
        }
        .ht-points-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #f9fafb;
        }
    </style>

    @php $stats = $this->stats; @endphp

    <div class="ht-points-stats">
        <div class="ht-points-stat">
            <div class="ht-points-stat-label">Total Points Earned</div>
            <div class="ht-points-stat-value">{{ number_format($stats['total_earned']) }}</div>
        </div>
        <div class="ht-points-stat">
            <div class="ht-points-stat-label">Total Points Redeemed</div>
            <div class="ht-points-stat-value">{{ number_format($stats['total_redeemed']) }}</div>
        </div>
        <div class="ht-points-stat">
            <div class="ht-points-stat-label">Total Redemptions</div>
            <div class="ht-points-stat-value">{{ number_format($stats['total_redemptions']) }}</div>
        </div>
        <div class="ht-points-stat">
            <div class="ht-points-stat-label">Active Points-Pro Users</div>
            <div class="ht-points-stat-value">{{ number_format($stats['active_points_pro']) }}</div>
        </div>
    </div>

    <form wire:submit="save">
        {{ $this->form }}
    </form>

    <div class="mt-8">
        <h2 class="text-lg font-semibold text-white mb-4">Adjust User Points</h2>
        <div class="rounded-xl border border-zinc-800 bg-zinc-900 p-5">
            <form wire:submit="adjustPoints">
                {{ $this->adjustForm }}
                <div class="mt-4">
                    <button type="submit" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white text-sm font-medium hover:bg-primary-500 transition-colors">
                        Adjust Points
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-filament-panels::page>
