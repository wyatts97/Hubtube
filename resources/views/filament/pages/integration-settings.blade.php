<x-filament-panels::page>
    {{-- SMTP / Bunny Stream Settings Form --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Settings
            </x-filament::button>
        </div>
    </form>

    {{-- Email Templates Section --}}
    <div class="mt-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Email Templates</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">Customize the content and subject lines for all outgoing emails. Use placeholders like <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">@{{ username }}</code> which will be replaced with real values.</p>
            </div>
            <button
                wire:click="seedTemplates"
                wire:loading.attr="disabled"
                class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
            >
                <x-heroicon-m-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="seedTemplates" />
                Reset to Defaults
            </button>
        </div>

        <div class="space-y-3">
            @forelse ($emailTemplates as $template)
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div class="flex items-center justify-between px-4 py-3 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50 transition"
                         wire:click="toggleTemplate({{ $template['id'] }})">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center">
                                <button
                                    wire:click.stop="toggleTemplateActive({{ $template['id'] }})"
                                    class="relative inline-flex h-5 w-9 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none {{ $template['is_active'] ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-600' }}"
                                    role="switch"
                                    aria-checked="{{ $template['is_active'] ? 'true' : 'false' }}"
                                >
                                    <span class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $template['is_active'] ? 'translate-x-4' : 'translate-x-0' }}"></span>
                                </button>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900 dark:text-white text-sm">{{ $template['name'] }}</span>
                                <span class="ml-2 text-xs text-gray-400 dark:text-gray-500 font-mono">{{ $template['slug'] }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if (!$template['is_active'])
                                <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">Disabled</span>
                            @endif
                            <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-400 transition-transform {{ $expandedTemplate === $template['id'] ? 'rotate-180' : '' }}" />
                        </div>
                    </div>

                    @if ($expandedTemplate === $template['id'])
                        <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-4 space-y-4">
                            @if ($template['description'])
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $template['description'] }}</p>
                            @endif

                            <div class="flex flex-wrap gap-1.5 mb-2">
                                <span class="text-xs text-gray-500 dark:text-gray-400 mr-1">Placeholders:</span>
                                @foreach ($this->getPlaceholders($template['slug']) as $placeholder)
                                    <code class="text-xs bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded cursor-pointer hover:bg-indigo-100 dark:hover:bg-indigo-900/50 transition"
                                          title="Click to copy"
                                          x-data
                                          x-on:click="navigator.clipboard.writeText('{{ $placeholder }}'); $tooltip('Copied!')">
                                        {{ $placeholder }}
                                    </code>
                                @endforeach
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                                <input
                                    type="text"
                                    wire:model.defer="editingTemplates.{{ $template['id'] }}.subject"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                                />
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Body (HTML)</label>
                                <textarea
                                    wire:model.defer="editingTemplates.{{ $template['id'] }}.body_html"
                                    rows="8"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm font-mono focus:border-primary-500 focus:ring-primary-500"
                                ></textarea>
                                <p class="text-xs text-gray-400 mt-1">HTML is supported. The body is wrapped in a branded email layout with header and footer automatically.</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <button
                                    wire:click="saveTemplate({{ $template['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700 transition"
                                >
                                    <x-heroicon-m-check class="w-4 h-4" />
                                    Save Template
                                </button>
                                <button
                                    wire:click="previewTemplate({{ $template['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                >
                                    <x-heroicon-m-eye class="w-4 h-4" />
                                    Preview
                                </button>
                                <button
                                    wire:click="sendTestTemplate({{ $template['id'] }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition"
                                >
                                    <x-heroicon-m-paper-airplane class="w-4 h-4" />
                                    Send Test
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                    <p>No email templates found.</p>
                    <button wire:click="seedTemplates" class="mt-2 text-primary-600 hover:underline text-sm">Create default templates</button>
                </div>
            @endforelse
        </div>
    </div>

    {{-- Email Preview Modal --}}
    @if ($previewHtml)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('previewHtml', null)">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-2xl max-h-[80vh] overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="font-medium text-gray-900 dark:text-white">Email Preview</h3>
                    <button wire:click="$set('previewHtml', null)" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                        <x-heroicon-m-x-mark class="w-5 h-5" />
                    </button>
                </div>
                <div class="overflow-auto max-h-[70vh]">
                    <iframe srcdoc="{{ e($previewHtml) }}" class="w-full h-[600px] border-0"></iframe>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
