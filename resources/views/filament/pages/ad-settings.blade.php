<x-filament-panels::page>
    {{-- ── Global Ad Settings Form ── --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save Ad Settings
            </x-filament::button>
        </div>
    </form>

    {{-- ── Video Ad Creatives Management ── --}}
    <div class="mt-10">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-xl font-bold text-white">Video Ad Creatives</h2>
                <p class="text-sm text-gray-400 mt-1">
                    Manage individual ad creatives for pre-roll, mid-roll, and post-roll placements. Supports VAST/VPAID tags, direct MP4 URLs, and HTML ad scripts.
                </p>
            </div>
            <x-filament::button wire:click="openAdForm" icon="heroicon-o-plus">
                Add Ad Creative
            </x-filament::button>
        </div>

        {{-- Ad Form Modal --}}
        @if($showAdForm)
        <div class="mb-6 p-6 rounded-xl border border-gray-700 bg-gray-900">
            <h3 class="text-lg font-semibold mb-4 text-white">
                {{ $editingAdId ? 'Edit Ad Creative' : 'New Ad Creative' }}
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-300">Name *</label>
                    <input type="text" wire:model="adFormData.name" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white" placeholder="e.g. Summer Sale Pre-Roll" />
                    @error('adFormData.name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-300">Placement *</label>
                    <select wire:model="adFormData.placement" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white">
                        <option value="pre_roll">Pre-Roll (before video)</option>
                        <option value="mid_roll">Mid-Roll (during video)</option>
                        <option value="post_roll">Post-Roll (after video)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-300">Ad Type *</label>
                    <select wire:model.live="adFormData.type" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white">
                        <option value="mp4">MP4 Video URL</option>
                        <option value="vast">VAST Tag URL</option>
                        <option value="vpaid">VPAID Tag URL</option>
                        <option value="html">HTML Ad Script</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-300">Weight / Priority</label>
                    <input type="number" wire:model="adFormData.weight" min="1" max="100" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white" />
                    <p class="text-xs text-gray-500 mt-1">Higher weight = more likely to be shown when shuffling</p>
                </div>
            </div>

            @if($adFormData['type'] === 'mp4')
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1 text-gray-300">Upload MP4 Video</label>
                <input type="file" wire:model="adVideoFile" accept="video/mp4,video/webm" class="block w-full text-sm text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-900 file:text-blue-300" />
                @error('adVideoFile') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Upload an MP4 or WebM file (max 100MB). Always stored locally.</p>
                @if($adVideoFile)
                    <p class="text-xs text-green-400 mt-1">✓ File selected: {{ $adVideoFile->getClientOriginalName() }}</p>
                @endif
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1 text-gray-300">Or External MP4 URL</label>
                <input type="text" wire:model="adFormData.content" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white font-mono text-sm" placeholder="https://example.com/ads/my-ad.mp4" />
                @error('adFormData.content') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Optional if uploading a file above. Use this for externally hosted MP4 URLs.</p>
            </div>
            @else
            <div class="mb-4">
                <label class="block text-sm font-medium mb-1 text-gray-300">Content *</label>
                <textarea wire:model="adFormData.content" rows="4" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white font-mono text-sm" placeholder="{{ $adFormData['type'] === 'html' ? '<script>...</script>' : 'https://example.com/vast-tag.xml' }}"></textarea>
                @error('adFormData.content') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">
                    @if($adFormData['type'] === 'vast')
                        VAST XML tag URL from your ad network
                    @elseif($adFormData['type'] === 'vpaid')
                        VPAID tag URL from your ad network
                    @else
                        Raw HTML/JavaScript ad code
                    @endif
                </p>
            </div>
            @endif

            <div class="mb-4">
                <label class="block text-sm font-medium mb-1 text-gray-300">Click-Through URL</label>
                <input type="url" wire:model="adFormData.click_url" class="fi-input block w-full rounded-lg border-gray-600 bg-gray-800 text-white font-mono text-sm" placeholder="https://example.com/landing-page" />
                @error('adFormData.click_url') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                <p class="text-xs text-gray-500 mt-1">Optional. When set, clicking the ad opens this URL in a new tab.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-300">Target Categories</label>
                    <p class="text-xs text-gray-500 mb-2">Leave empty to show on all categories</p>
                    <div class="max-h-40 overflow-y-auto border rounded-lg p-2 border-gray-600">
                        @foreach($this->categories as $catId => $catName)
                            <label class="flex items-center gap-2 py-1 cursor-pointer">
                                <input type="checkbox" wire:model="adFormData.category_ids" value="{{ $catId }}" class="rounded border-gray-600" />
                                <span class="text-sm text-gray-300">{{ $catName }}</span>
                            </label>
                        @endforeach
                        @if(empty($this->categories))
                            <p class="text-xs text-gray-400 py-2">No categories found</p>
                        @endif
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-300">Target User Roles</label>
                    <p class="text-xs text-gray-500 mb-2">Leave empty to show to all users</p>
                    <div class="border rounded-lg p-2 border-gray-600">
                        @foreach(['guest' => 'Guests (not logged in)', 'default' => 'Default Users (free)', 'pro' => 'Pro Users', 'admin' => 'Admins'] as $role => $label)
                            <label class="flex items-center gap-2 py-1 cursor-pointer">
                                <input type="checkbox" wire:model="adFormData.target_roles" value="{{ $role }}" class="rounded border-gray-600" />
                                <span class="text-sm text-gray-300">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-2 mb-4">
                <input type="checkbox" wire:model="adFormData.is_active" id="ad_active" class="rounded border-gray-600" />
                <label for="ad_active" class="text-sm text-gray-300">Active</label>
            </div>

            <div class="flex gap-3">
                <x-filament::button wire:click="saveAd">
                    {{ $editingAdId ? 'Update Ad' : 'Create Ad' }}
                </x-filament::button>
                <x-filament::button color="gray" wire:click="cancelAdForm">
                    Cancel
                </x-filament::button>
            </div>
        </div>
        @endif

        {{-- Ads Table --}}
        <div class="overflow-x-auto rounded-xl border border-gray-700 bg-gray-900">
            <table class="w-full text-sm">
                <thead class="bg-gray-800">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-300">Name</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-300">Type</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-300">Placement</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-300">Weight</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-300">Targeting</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-300">Status</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-300">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-700">
                    @forelse($this->videoAds as $ad)
                        <tr class="bg-gray-900 hover:bg-gray-800/50 {{ !$ad->is_active ? 'opacity-50' : '' }}">
                            <td class="px-4 py-3 text-white font-medium">{{ $ad->name }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $ad->type === 'mp4' ? 'bg-blue-900 text-blue-300' : '' }}
                                    {{ $ad->type === 'vast' ? 'bg-purple-900 text-purple-300' : '' }}
                                    {{ $ad->type === 'vpaid' ? 'bg-indigo-900 text-indigo-300' : '' }}
                                    {{ $ad->type === 'html' ? 'bg-amber-900 text-amber-300' : '' }}
                                ">
                                    {{ strtoupper($ad->type) }}
                                </span>
                                @if($ad->file_path)
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-900 text-green-300 ml-1">
                                        Local
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium
                                    {{ $ad->placement === 'pre_roll' ? 'bg-green-900 text-green-300' : '' }}
                                    {{ $ad->placement === 'mid_roll' ? 'bg-yellow-900 text-yellow-300' : '' }}
                                    {{ $ad->placement === 'post_roll' ? 'bg-red-900 text-red-300' : '' }}
                                ">
                                    {{ str_replace('_', '-', $ad->placement) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-300">{{ $ad->weight }}</td>
                            <td class="px-4 py-3 text-xs text-gray-400">
                                @if($ad->category_ids && count($ad->category_ids))
                                    <span class="text-gray-500">{{ count($ad->category_ids) }} categories</span>
                                @else
                                    <span class="text-gray-400">All categories</span>
                                @endif
                                <br>
                                @if($ad->target_roles && count($ad->target_roles))
                                    <span class="text-gray-500">{{ implode(', ', $ad->target_roles) }}</span>
                                @else
                                    <span class="text-gray-400">All roles</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <button wire:click="toggleAdActive({{ $ad->id }})" class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium cursor-pointer
                                    {{ $ad->is_active ? 'bg-green-900 text-green-300' : 'bg-gray-700 text-gray-400' }}
                                ">
                                    {{ $ad->is_active ? 'Active' : 'Inactive' }}
                                </button>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button wire:click="openAdForm({{ $ad->id }})" class="text-blue-400 hover:text-blue-300 text-xs font-medium">
                                        Edit
                                    </button>
                                    <button wire:click="deleteAd({{ $ad->id }})" wire:confirm="Delete this ad creative?" class="text-red-400 hover:text-red-300 text-xs font-medium">
                                        Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                                No ad creatives yet. Click "Add Ad Creative" to create your first one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Ad Size Guidelines --}}
    <div class="mt-8 p-4 rounded-lg bg-gray-800">
        <h3 class="text-lg font-semibold mb-4 text-white">Ad Guidelines</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
            <div>
                <h4 class="font-medium mb-2 text-gray-200">Video Roll Ads</h4>
                <ul class="list-disc list-inside text-gray-400 space-y-1">
                    <li><strong>MP4</strong> — Direct URL to a video file</li>
                    <li><strong>VAST</strong> — XML tag URL from ad network</li>
                    <li><strong>VPAID</strong> — Interactive ad tag URL</li>
                    <li><strong>HTML</strong> — Raw HTML/JS ad script</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium mb-2 text-gray-200">Display Ads (Grid/Sidebar)</h4>
                <ul class="list-disc list-inside text-gray-400 space-y-1">
                    <li><strong>300×250</strong> — Medium Rectangle</li>
                    <li><strong>300×600</strong> — Large Skyscraper</li>
                    <li><strong>336×280</strong> — Large Rectangle</li>
                </ul>
            </div>
            <div>
                <h4 class="font-medium mb-2 text-gray-200">Targeting Tips</h4>
                <ul class="list-disc list-inside text-gray-400 space-y-1">
                    <li>Leave categories empty → all categories</li>
                    <li>Leave roles empty → all users</li>
                    <li>Higher weight → more frequent in shuffle</li>
                    <li>Weight 1 = normal, 10 = 10× more likely</li>
                </ul>
            </div>
        </div>
    </div>
</x-filament-panels::page>
