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
        <x-filament::section heading="Adjust User Points" class="fi-section" icon="phosphor-coins">
            <form wire:submit="adjustPoints" class="space-y-4">
                {{ $this->adjustForm }}
                <div class="flex justify-end">
                    <x-filament::button
                        type="submit"
                        color="primary"
                        wire:loading.attr="disabled"
                        wire:target="adjustPoints"
                    >
                        <span wire:loading.remove wire:target="adjustPoints">Adjust Points</span>
                        <span wire:loading wire:target="adjustPoints">Adjusting...</span>
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>
    </div>
</x-filament-panels::page>
