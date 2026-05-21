<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Translation;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\TranslationService;
use App\Services\VideoDescriptionTemplate;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SeoDiagnostics extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'phosphor-clipboard-text';
    protected static ?string $navigationLabel = 'SEO Diagnostics';
    protected static string | \UnitEnum | null $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'seo_video_default_description_template' => Setting::get(
                VideoDescriptionTemplate::SETTING_KEY,
                VideoDescriptionTemplate::DEFAULT_TEMPLATE,
            ),
            'fill_only_public' => true,
            'fill_only_approved' => true,
            'fill_only_processed' => true,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('SEO Diagnostics')
                    ->tabs([
                        Tab::make('Overview')
                            ->icon('phosphor-chart-bar')
                            ->schema([
                                Section::make('Content health')
                                    ->description('Counts of videos and metadata coverage that affect search visibility.')
                                    ->schema([
                                        Placeholder::make('content_stats')
                                            ->label('')
                                            ->content(fn () => new HtmlString($this->renderContentStatsHtml())),
                                    ]),

                                Section::make('Translation coverage')
                                    ->description('Translated slugs and titles per enabled locale (videos only).')
                                    ->schema([
                                        Placeholder::make('translation_stats')
                                            ->label('')
                                            ->content(fn () => new HtmlString($this->renderTranslationStatsHtml())),
                                    ]),

                                Section::make('Sitemap')
                                    ->description('Live links to each child sitemap with current entry counts.')
                                    ->schema([
                                        Placeholder::make('sitemap_stats')
                                            ->label('')
                                            ->content(fn () => new HtmlString($this->renderSitemapStatsHtml())),
                                    ]),
                            ]),

                        Tab::make('Default Descriptions')
                            ->icon('phosphor-file-text')
                            ->schema([
                                Placeholder::make('description_help')
                                    ->label('')
                                    ->content(new HtmlString(
                                        '<div class="text-sm p-3 rounded-lg" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3);">' .
                                        '<strong>📝 Mass-fill missing video descriptions</strong><br>' .
                                        'Apply a description template to every video that currently has no description. ' .
                                        'This is purely a content fill — search engines and viewers prefer pages with body text, ' .
                                        'and missing descriptions are the single biggest SEO miss on most video sites.<br><br>' .
                                        '<strong>Available variables:</strong> ' .
                                        '<code>{title}</code>, <code>{category}</code>, <code>{site_name}</code>, ' .
                                        '<code>{uploader}</code>, <code>{tags}</code>, <code>{duration}</code>, ' .
                                        '<code>{views}</code>, <code>{year}</code>' .
                                        '</div>'
                                    )),

                                Section::make('Description template')
                                    ->schema([
                                        Textarea::make('seo_video_default_description_template')
                                            ->label('Template')
                                            ->rows(3)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->placeholder(VideoDescriptionTemplate::DEFAULT_TEMPLATE)
                                            ->helperText('This template is also used as the fallback whenever a video has no description.'),

                                        Placeholder::make('description_preview')
                                            ->label('Preview (against latest public video)')
                                            ->content(function (callable $get) {
                                                $tpl = (string) ($get('seo_video_default_description_template') ?: VideoDescriptionTemplate::DEFAULT_TEMPLATE);
                                                $sample = Video::query()
                                                    ->with(['user:id,username', 'category:id,name'])
                                                    ->public()->approved()->processed()
                                                    ->latest('published_at')->first();
                                                if (!$sample) {
                                                    return new HtmlString('<em class="text-gray-500">No published videos found to preview against.</em>');
                                                }
                                                $rendered = VideoDescriptionTemplate::render($tpl, $sample);
                                                $missing = VideoDescriptionTemplate::missingCount();
                                                return new HtmlString(
                                                    '<div class="p-3 rounded-md border border-gray-300/50 dark:border-gray-700 text-sm">' .
                                                    e($rendered) .
                                                    '</div>' .
                                                    '<p class="text-xs text-gray-500 mt-2">' .
                                                    "<strong>{$missing}</strong> public, approved, processed video(s) currently have no description and would receive this." .
                                                    '</p>'
                                                );
                                            }),
                                    ]),

                                Section::make('Mass-apply scope')
                                    ->schema([
                                        Toggle::make('fill_only_public')
                                            ->label('Public videos only')
                                            ->helperText('Skip private/unlisted.')
                                            ->default(true),
                                        Toggle::make('fill_only_approved')
                                            ->label('Approved videos only')
                                            ->helperText('Skip pending moderation.')
                                            ->default(true),
                                        Toggle::make('fill_only_processed')
                                            ->label('Processed videos only')
                                            ->helperText('Skip videos still uploading or transcoding.')
                                            ->default(true),
                                    ])->columns(3),

                                Actions::make([
                                    Action::make('previewMissing')
                                        ->label('Refresh preview / count')
                                        ->icon('phosphor-arrows-clockwise')
                                        ->color('gray')
                                        ->action(function () {
                                            // Form is reactive — just notify so the user knows it refreshed.
                                            Notification::make()
                                                ->title('Preview refreshed')
                                                ->success()
                                                ->send();
                                        }),

                                    Action::make('saveTemplate')
                                        ->label('Save Template')
                                        ->icon('phosphor-check')
                                        ->color('primary')
                                        ->action(fn () => $this->saveTemplate()),

                                    Action::make('applyToMissing')
                                        ->label('Apply to videos missing a description')
                                        ->icon('phosphor-sparkle')
                                        ->color('success')
                                        ->requiresConfirmation()
                                        ->modalHeading('Apply default description?')
                                        ->modalDescription('Every video matching the selected scope that has no description will be updated with the rendered template. This action cannot be undone in bulk.')
                                        ->modalSubmitActionLabel('Apply now')
                                        ->action(fn () => $this->applyToMissing()),

                                    Action::make('dryRun')
                                        ->label('Dry run')
                                        ->icon('phosphor-eye')
                                        ->color('warning')
                                        ->action(fn () => $this->applyToMissing(true)),
                                ])->columnSpanFull(),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function saveTemplate(): void
    {
        $data = $this->form->getState();
        $tpl = (string) ($data['seo_video_default_description_template'] ?? VideoDescriptionTemplate::DEFAULT_TEMPLATE);

        Setting::set(VideoDescriptionTemplate::SETTING_KEY, $tpl, 'seo', 'string');
        AdminLogger::settingsSaved('SEO Diagnostics', [VideoDescriptionTemplate::SETTING_KEY]);

        Notification::make()
            ->title('Description template saved')
            ->success()
            ->send();
    }

    public function applyToMissing(bool $dryRun = false): void
    {
        $data = $this->form->getState();
        $tpl = (string) ($data['seo_video_default_description_template'] ?? VideoDescriptionTemplate::DEFAULT_TEMPLATE);

        // Persist the template alongside the apply so settings stay in sync
        if (!$dryRun) {
            Setting::set(VideoDescriptionTemplate::SETTING_KEY, $tpl, 'seo', 'string');
        }

        $opts = [
            'only_public'    => (bool) ($data['fill_only_public']    ?? true),
            'only_approved'  => (bool) ($data['fill_only_approved']  ?? true),
            'only_processed' => (bool) ($data['fill_only_processed'] ?? true),
            'dry_run'        => $dryRun,
        ];

        $count = VideoDescriptionTemplate::applyToMissing($tpl, $opts);

        if (!$dryRun) {
            AdminLogger::log(
                'SEO Diagnostics — bulk-filled descriptions',
                'admin',
                ['updated' => $count, 'template' => $tpl],
            );
        }

        Notification::make()
            ->title($dryRun
                ? "Dry run: {$count} video(s) would be updated"
                : "Applied default description to {$count} video(s)")
            ->success()
            ->send();
    }

    // ──────────────────────────────────────────────────────────────
    // Stats renderers
    // ──────────────────────────────────────────────────────────────

    protected function renderContentStatsHtml(): string
    {
        $total      = Video::count();
        $indexable  = Video::query()->public()->approved()->processed()->count();
        $missingDesc = Video::query()->public()->approved()->processed()
            ->where(fn ($q) => $q->whereNull('description')->orWhere('description', ''))
            ->count();
        $missingThumb = Video::query()->public()->approved()->processed()
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNull('thumbnail')->orWhere('thumbnail', '');
                })->where(function ($q2) {
                    $q2->whereNull('external_thumbnail_url')->orWhere('external_thumbnail_url', '');
                });
            })
            ->count();
        $missingCategory = Video::query()->public()->approved()->processed()
            ->whereNull('category_id')
            ->count();
        $missingTags = Video::query()->public()->approved()->processed()
            ->where(function ($q) {
                $q->whereNull('tags')->orWhere('tags', '[]')->orWhere('tags', '');
            })
            ->count();
        $totalCategories = Category::count();
        $emptyCategories = Category::query()
            ->whereDoesntHave('videos', fn ($q) => $q->public()->approved()->processed())
            ->count();

        $card = function (string $label, int|string $value, ?string $hint = null, string $tone = 'default') {
            $border = match ($tone) {
                'warn'    => 'border-amber-500/40',
                'danger'  => 'border-rose-500/40',
                'success' => 'border-emerald-500/40',
                default   => 'border-gray-500/30',
            };
            $hintHtml = $hint ? '<div class="text-xs text-gray-500 mt-1">' . e($hint) . '</div>' : '';
            return '<div class="p-3 rounded-md border ' . $border . '">' .
                '<div class="text-xs uppercase tracking-wide text-gray-500">' . e($label) . '</div>' .
                '<div class="text-2xl font-semibold mt-1">' . e((string) $value) . '</div>' .
                $hintHtml .
                '</div>';
        };

        return '<div class="grid grid-cols-2 md:grid-cols-4 gap-3">' .
            $card('Total videos', $total) .
            $card('Indexable (public+approved+processed)', $indexable, 'Visible to crawlers') .
            $card('Missing description', $missingDesc, $missingDesc > 0 ? 'Run "Apply to videos missing a description"' : null, $missingDesc > 0 ? 'warn' : 'success') .
            $card('Missing thumbnail', $missingThumb, $missingThumb > 0 ? 'No og:image, no rich results' : null, $missingThumb > 0 ? 'danger' : 'success') .
            $card('Missing category', $missingCategory, null, $missingCategory > 0 ? 'warn' : 'default') .
            $card('Missing tags', $missingTags) .
            $card('Categories', $totalCategories) .
            $card('Empty categories', $emptyCategories, $emptyCategories > 0 ? 'Hidden from category sitemap' : null) .
            '</div>';
    }

    protected function renderTranslationStatsHtml(): string
    {
        $locales = TranslationService::getEnabledLocales();
        $default = TranslationService::getDefaultLocale();

        if (count($locales) <= 1) {
            return '<em class="text-gray-500">Multi-language is not enabled. Enable additional languages in Language Settings to see translation coverage.</em>';
        }

        $indexable = Video::query()->public()->approved()->processed()->count();

        $rows = '';
        foreach ($locales as $locale) {
            if ($locale === $default) {
                $rows .= '<tr><td class="py-1 pr-4 font-mono">' . e($locale) . ' <span class="text-xs text-gray-500">(default)</span></td>' .
                    '<td class="py-1 pr-4">' . number_format($indexable) . '</td>' .
                    '<td class="py-1 pr-4 text-gray-500">—</td></tr>';
                continue;
            }
            $titleCount = Translation::where('translatable_type', Video::class)
                ->where('locale', $locale)
                ->where('field', 'title')
                ->count();
            $slugCount = Translation::where('translatable_type', Video::class)
                ->where('locale', $locale)
                ->where('field', 'title')
                ->whereNotNull('translated_slug')
                ->where('translated_slug', '!=', '')
                ->count();
            $coverage = $indexable > 0 ? round(($titleCount / $indexable) * 100, 1) : 0;
            $rows .= '<tr>' .
                '<td class="py-1 pr-4 font-mono">' . e($locale) . '</td>' .
                '<td class="py-1 pr-4">' . number_format($titleCount) . ' <span class="text-xs text-gray-500">(' . $coverage . '%)</span></td>' .
                '<td class="py-1 pr-4">' . number_format($slugCount) . '</td>' .
                '</tr>';
        }

        return '<table class="text-sm w-full"><thead><tr class="text-xs uppercase text-gray-500 text-left">' .
            '<th class="py-1 pr-4">Locale</th>' .
            '<th class="py-1 pr-4">Translated titles</th>' .
            '<th class="py-1 pr-4">With translated slug</th>' .
            '</tr></thead><tbody>' .
            $rows .
            '</tbody></table>' .
            '<p class="text-xs text-gray-500 mt-2">Run <code>php artisan translations:backfill-slugs</code> to regenerate any missing translated slugs.</p>';
    }

    protected function renderSitemapStatsHtml(): string
    {
        $videoCount = Video::query()->public()->approved()->processed()->count();
        $chunk = (int) Setting::get('seo_sitemap_chunk_size', 10000);
        $chunk = max(100, min($chunk, 50000));
        $videoChunks = max(1, (int) ceil($videoCount / $chunk));

        $rows = [
            ['Sitemap index', '/sitemap.xml', '—'],
            ['Pages', '/sitemap-pages.xml', '—'],
            ['Categories', '/sitemap-categories.xml', (string) Category::query()
                ->whereNull('parent_id')
                ->whereHas('videos', fn ($q) => $q->public()->approved()->processed())
                ->count()],
            ['Tags', '/sitemap-tags.xml', '—'],
            ['Channels', '/sitemap-channels.xml', '—'],
            ['Videos (chunk 1)', '/sitemap-videos.xml', number_format(min($videoCount, $chunk))],
        ];
        if ($videoChunks > 1) {
            for ($i = 2; $i <= $videoChunks; $i++) {
                $count = min($videoCount - ($chunk * ($i - 1)), $chunk);
                $rows[] = ['Videos (chunk ' . $i . ')', "/sitemap-videos-{$i}.xml", number_format($count)];
            }
        }
        if (Setting::get('seo_sitemap_images_enabled', true)) {
            $rows[] = ['Images', '/sitemap-images.xml', '—'];
        }
        if (Setting::get('seo_sitemap_galleries_enabled', true)) {
            $rows[] = ['Galleries', '/sitemap-galleries.xml', '—'];
        }
        if (Setting::get('seo_sitemap_playlists_enabled', true)) {
            $rows[] = ['Playlists', '/sitemap-playlists.xml', '—'];
        }

        $html = '<table class="text-sm w-full"><thead><tr class="text-xs uppercase text-gray-500 text-left">' .
            '<th class="py-1 pr-4">Sitemap</th>' .
            '<th class="py-1 pr-4">URL</th>' .
            '<th class="py-1 pr-4">Approx. count</th>' .
            '</tr></thead><tbody>';
        foreach ($rows as [$label, $path, $count]) {
            $absUrl = url($path);
            $html .= '<tr>' .
                '<td class="py-1 pr-4">' . e($label) . '</td>' .
                '<td class="py-1 pr-4"><a href="' . e($absUrl) . '" target="_blank" class="text-primary-500 underline font-mono text-xs">' . e($path) . '</a></td>' .
                '<td class="py-1 pr-4">' . e($count) . '</td>' .
                '</tr>';
        }
        $html .= '</tbody></table>';
        $html .= '<p class="text-xs text-gray-500 mt-2">Total indexable videos: <strong>' . number_format($videoCount) . '</strong> across ' . $videoChunks . ' chunk(s) of ' . number_format($chunk) . '.</p>';

        return $html;
    }

    public function save(): void
    {
        // Reuse Save button on the shared blade template — saves only the template setting.
        $this->saveTemplate();
    }
}
