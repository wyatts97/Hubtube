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
            background-color: #111827;
            border: 1px solid #374151;
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
</x-filament-panels::page>
