<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Ad Settings
            </x-filament::button>
        </div>
    </form>
    
    <div class="mt-8 p-4 rounded-lg bg-gray-100 dark:bg-gray-800">
        <h3 class="text-lg font-semibold mb-4">Ad Size Guidelines</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
            <div>
                <h4 class="font-medium mb-2">Video Grid Ads (Between Cards)</h4>
                <ul class="list-disc list-inside text-gray-600 dark:text-gray-400">
                    <li><strong>300x250</strong> - Medium Rectangle (Recommended)</li>
                    <li><strong>336x280</strong> - Large Rectangle</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium mb-2">Video Page Sidebar Ads</h4>
                <ul class="list-disc list-inside text-gray-600 dark:text-gray-400">
                    <li><strong>300x250</strong> - Medium Rectangle</li>
                    <li><strong>300x300</strong> - Square</li>
                    <li><strong>300x500</strong> - Half Page</li>
                    <li><strong>300x600</strong> - Large Skyscraper</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
