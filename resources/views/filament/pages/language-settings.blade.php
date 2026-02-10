<x-filament-panels::page>
    {{-- Language Settings Form --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex gap-3">
            <x-filament::button type="submit">
                Save Language Settings
            </x-filament::button>
        </div>
    </form>

    {{-- Translation Overrides Section --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold text-gray-900 dark:text-white">Translation Overrides</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Fix or replace words/phrases that Google Translate gets wrong. For example, set "wedgie" → the correct term in each language.
                    Overrides apply to both dynamic content and static UI translations.
                </p>
            </div>
        </div>

        {{-- Add/Edit Override Form --}}
        <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 mb-6">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">
                {{ $editingOverrideId ? '✏️ Edit Override' : '➕ Add New Override' }}
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Language</label>
                    <select wire:model="overrideLocale" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                        @foreach($this->localeOptions as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 mt-1">"All Languages" applies to every locale</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Wrong Word/Phrase</label>
                    <input type="text" wire:model="overrideOriginal" placeholder="e.g. cuña (wrong translation)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <p class="text-xs text-gray-400 mt-1">The incorrect text that appears after translation</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Correct Replacement</label>
                    <input type="text" wire:model="overrideReplacement" placeholder="e.g. wedgie" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                    <p class="text-xs text-gray-400 mt-1">What it should say instead</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Notes (optional)</label>
                    <input type="text" wire:model="overrideNotes" placeholder="e.g. Slang term, keep in English" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm">
                </div>
            </div>
            <div class="flex items-center gap-4 mt-3">
                <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <input type="checkbox" wire:model="overrideCaseSensitive" class="rounded border-gray-300 dark:border-gray-600">
                    Case-sensitive match
                </label>
                <div class="flex gap-2 ml-auto">
                    @if($editingOverrideId)
                        <x-filament::button color="gray" wire:click="resetOverrideForm" size="sm">
                            Cancel
                        </x-filament::button>
                    @endif
                    <x-filament::button wire:click="saveOverride" size="sm">
                        {{ $editingOverrideId ? 'Update Override' : 'Add Override' }}
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Filter + Actions --}}
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400">Filter:</label>
                <select wire:model.live="overrideFilterLocale" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm py-1">
                    <option value="">All Languages</option>
                    @foreach($this->localeOptions as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ml-auto">
                <x-filament::button color="danger" size="xs" wire:click="clearTranslationCache" wire:confirm="This will delete ALL cached translations. They will be re-translated (with overrides applied) on next request. Continue?">
                    Clear Translation Cache
                </x-filament::button>
            </div>
        </div>

        {{-- Overrides Table --}}
        @if($this->overrides->count() > 0)
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Language</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Wrong Text</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">→</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Correct Text</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Notes</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($this->overrides as $override)
                            <tr class="{{ !$override->is_active ? 'opacity-50' : '' }} hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if($override->locale === '*')
                                        <span class="text-xs font-medium px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">🌐 All</span>
                                    @else
                                        @php $lang = \App\Services\TranslationService::LANGUAGES[$override->locale] ?? null; @endphp
                                        <span class="text-xs">{{ $lang ? $lang['flag'] . ' ' . $lang['native'] : $override->locale }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5">
                                    <code class="text-xs px-1.5 py-0.5 rounded bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300">{{ $override->original_text }}</code>
                                </td>
                                <td class="px-4 py-2.5 text-gray-400">→</td>
                                <td class="px-4 py-2.5">
                                    <code class="text-xs px-1.5 py-0.5 rounded bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300">{{ $override->replacement_text }}</code>
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500 dark:text-gray-400 max-w-[200px] truncate">{{ $override->notes ?? '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <button wire:click="toggleOverride({{ $override->id }})" class="text-xs font-medium px-2 py-0.5 rounded-full cursor-pointer {{ $override->is_active ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' }}">
                                        {{ $override->is_active ? 'Active' : 'Disabled' }}
                                    </button>
                                </td>
                                <td class="px-4 py-2.5 text-right whitespace-nowrap">
                                    <button wire:click="editOverride({{ $override->id }})" class="text-xs text-blue-600 dark:text-blue-400 hover:underline mr-2">Edit</button>
                                    <button wire:click="deleteOverride({{ $override->id }})" wire:confirm="Delete this override?" class="text-xs text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="text-center py-8 text-gray-500 dark:text-gray-400 border border-dashed border-gray-300 dark:border-gray-600 rounded-xl">
                <p class="text-sm">No translation overrides yet.</p>
                <p class="text-xs mt-1">Add overrides above to fix words that Google Translate gets wrong — like niche slang or brand terms.</p>
            </div>
        @endif
    </div>

    {{-- Info Boxes --}}
    <div class="mt-8 p-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 border border-gray-200 dark:border-gray-700">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">Generate UI Translation Files</h3>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
            After enabling new languages or adding overrides, run this command on the server to auto-generate the UI translation JSON files:
        </p>
        <code class="block p-3 rounded-lg bg-gray-900 text-green-400 text-sm font-mono">
            php artisan translations:generate --force
        </code>
        <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
            This uses Google Translate to translate all UI text, then applies your overrides on top.
            Then run <code>npm run build</code> to include them in the frontend.
        </p>
    </div>

    <div class="mt-4 p-4 rounded-xl bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800">
        <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-200 mb-2">💡 Override Tips</h3>
        <ul class="text-sm text-amber-800 dark:text-amber-300 space-y-1 list-disc list-inside">
            <li><strong>"All Languages"</strong> overrides apply everywhere — great for brand names or slang that should never be translated (e.g. "wedgie")</li>
            <li><strong>Language-specific</strong> overrides let you fix a bad translation in just one language</li>
            <li><strong>Dynamic content</strong> overrides apply immediately — clear the translation cache to re-translate existing cached content</li>
            <li><strong>Static UI</strong> overrides require re-running <code>php artisan translations:generate --force</code> + <code>npm run build</code></li>
        </ul>
    </div>

    <div class="mt-4 p-4 rounded-xl bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
        <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-2">How Translation Works</h3>
        <ul class="text-sm text-blue-800 dark:text-blue-300 space-y-1 list-disc list-inside">
            <li><strong>Static UI</strong> — Buttons, labels, navigation translated via JSON files (generated once)</li>
            <li><strong>Dynamic Content</strong> — Video titles, descriptions auto-translated on-demand and cached in DB</li>
            <li><strong>Overrides</strong> — Your word/phrase corrections are applied after Google Translate runs</li>
            <li><strong>SEO URLs</strong> — Each language gets its own URL prefix (e.g. <code>/es/trending</code>, <code>/fr/video-slug</code>)</li>
            <li><strong>hreflang Tags</strong> — Automatically added for search engine indexing of all language versions</li>
        </ul>
    </div>
</x-filament-panels::page>
