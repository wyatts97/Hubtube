<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="submit">
                Save Language Settings
            </x-filament::button>
        </div>
    </form>

    <div class="mt-8 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Generate UI Translation Files</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            After enabling new languages, run this command on the server to auto-generate the UI translation JSON files:
        </p>
        <code class="block p-3 rounded-lg bg-gray-900 text-green-400 text-sm font-mono">
            php artisan translations:generate
        </code>
        <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
            This uses Google Translate to automatically translate all button labels, navigation items, and other UI text.
            Add <code>--force</code> to overwrite existing files. Then run <code>npm run build</code> to include them in the frontend.
        </p>
    </div>

    <div class="mt-4 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
        <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-2">How Translation Works</h3>
        <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1 list-disc list-inside">
            <li><strong>Static UI</strong> — Buttons, labels, navigation translated via JSON files (generated once)</li>
            <li><strong>Dynamic Content</strong> — Video titles, descriptions auto-translated on-demand and cached in DB</li>
            <li><strong>SEO URLs</strong> — Each language gets its own URL prefix (e.g. <code>/es/trending</code>, <code>/fr/video-slug</code>)</li>
            <li><strong>hreflang Tags</strong> — Automatically added for search engine indexing of all language versions</li>
            <li><strong>Language Switcher</strong> — Appears in the sidebar when 2+ languages are enabled</li>
        </ul>
    </div>
</x-filament-panels::page>
