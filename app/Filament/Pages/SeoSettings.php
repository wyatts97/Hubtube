<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SeoSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-magnifying-glass';
    protected static ?string $navigationLabel = 'SEO Settings';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 2;
    protected static string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            // Global SEO
            'seo_site_title' => Setting::get('seo_site_title', ''),
            'seo_title_separator' => Setting::get('seo_title_separator', '|'),
            'seo_meta_description' => Setting::get('seo_meta_description', ''),
            'seo_meta_keywords' => Setting::get('seo_meta_keywords', ''),
            'seo_og_image' => Setting::get('seo_og_image', ''),
            'seo_og_type' => Setting::get('seo_og_type', 'website'),
            'seo_twitter_card' => Setting::get('seo_twitter_card', 'summary_large_image'),
            'seo_twitter_site' => Setting::get('seo_twitter_site', ''),
            'seo_locale' => Setting::get('seo_locale', 'en_US'),

            // Verification Codes
            'seo_google_verification' => Setting::get('seo_google_verification', ''),
            'seo_bing_verification' => Setting::get('seo_bing_verification', ''),
            'seo_yandex_verification' => Setting::get('seo_yandex_verification', ''),
            'seo_pinterest_verification' => Setting::get('seo_pinterest_verification', ''),

            // Robots & Indexing
            'seo_robots_txt' => Setting::get('seo_robots_txt', "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /api/\nDisallow: /install\nDisallow: /login\nDisallow: /register\nDisallow: /settings\nDisallow: /wallet\nDisallow: /upload\nDisallow: /email/\n\nSitemap: " . url('/sitemap.xml')),
            'seo_noindex_private_videos' => Setting::get('seo_noindex_private_videos', true),
            'seo_noindex_user_pages' => Setting::get('seo_noindex_user_pages', false),

            // Schema / Structured Data
            'seo_schema_enabled' => Setting::get('seo_schema_enabled', true),
            'seo_schema_org_name' => Setting::get('seo_schema_org_name', ''),
            'seo_schema_org_logo' => Setting::get('seo_schema_org_logo', ''),
            'seo_schema_org_url' => Setting::get('seo_schema_org_url', ''),
            'seo_schema_same_as' => Setting::get('seo_schema_same_as', ''),

            // Video SEO
            'seo_video_schema_enabled' => Setting::get('seo_video_schema_enabled', true),
            'seo_video_title_template' => Setting::get('seo_video_title_template', '{title} | {site_name}'),
            'seo_video_description_template' => Setting::get('seo_video_description_template', '{description}'),
            'seo_video_auto_description' => Setting::get('seo_video_auto_description', true),
            'seo_video_description_fallback' => Setting::get('seo_video_description_fallback', 'Watch {title} on {site_name}. {category} video uploaded by {uploader}.'),
            'seo_video_thumbnail_alt_template' => Setting::get('seo_video_thumbnail_alt_template', '{title} - Video Thumbnail'),
            'seo_video_embed_enabled' => Setting::get('seo_video_embed_enabled', true),

            // Channel SEO
            'seo_channel_title_template' => Setting::get('seo_channel_title_template', '{channel_name} | {site_name}'),
            'seo_channel_description_template' => Setting::get('seo_channel_description_template', 'Watch videos from {channel_name} on {site_name}. {subscriber_count} subscribers.'),

            // Category SEO
            'seo_category_title_template' => Setting::get('seo_category_title_template', '{category_name} Videos | {site_name}'),
            'seo_category_description_template' => Setting::get('seo_category_description_template', 'Browse {category_name} videos on {site_name}. Find the best {category_name} content.'),

            // Page-specific titles
            'seo_home_title' => Setting::get('seo_home_title', ''),
            'seo_home_description' => Setting::get('seo_home_description', ''),
            'seo_trending_title' => Setting::get('seo_trending_title', 'Trending Videos | {site_name}'),
            'seo_trending_description' => Setting::get('seo_trending_description', 'Watch the most popular trending videos on {site_name} right now.'),
            'seo_shorts_title' => Setting::get('seo_shorts_title', 'Shorts | {site_name}'),
            'seo_shorts_description' => Setting::get('seo_shorts_description', 'Watch short-form videos on {site_name}.'),
            'seo_search_title' => Setting::get('seo_search_title', 'Search Results for "{query}" | {site_name}'),
            'seo_live_title' => Setting::get('seo_live_title', 'Live Streams | {site_name}'),
            'seo_live_description' => Setting::get('seo_live_description', 'Watch live streams on {site_name}.'),

            // Sitemap
            'seo_sitemap_video_enabled' => Setting::get('seo_sitemap_video_enabled', true),
            'seo_sitemap_max_videos' => Setting::get('seo_sitemap_max_videos', 10000),
            'seo_sitemap_max_channels' => Setting::get('seo_sitemap_max_channels', 5000),

            // Canonical
            'seo_canonical_enabled' => Setting::get('seo_canonical_enabled', true),
            'seo_force_trailing_slash' => Setting::get('seo_force_trailing_slash', false),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('SEO Settings')
                    ->tabs([
                        Tabs\Tab::make('Global SEO')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('Site Meta Tags')
                                    ->description('Default meta tags applied to all pages unless overridden.')
                                    ->schema([
                                        TextInput::make('seo_site_title')
                                            ->label('Default Site Title')
                                            ->placeholder('My Video Site - Watch Free Videos')
                                            ->helperText('Used as the default <title> tag when no page-specific title is set.')
                                            ->maxLength(70),
                                        Select::make('seo_title_separator')
                                            ->label('Title Separator')
                                            ->options([
                                                '|' => '| (pipe)',
                                                '-' => '- (dash)',
                                                '–' => '– (en dash)',
                                                '—' => '— (em dash)',
                                                '·' => '· (middle dot)',
                                                '>' => '> (arrow)',
                                                '»' => '» (guillemet)',
                                            ])
                                            ->default('|'),
                                        Textarea::make('seo_meta_description')
                                            ->label('Default Meta Description')
                                            ->rows(3)
                                            ->maxLength(160)
                                            ->helperText('Keep under 160 characters. Shown in search engine results.'),
                                        TextInput::make('seo_meta_keywords')
                                            ->label('Default Meta Keywords')
                                            ->placeholder('videos, streaming, entertainment')
                                            ->helperText('Comma-separated. Low SEO value but used by Yandex.'),
                                    ])->columns(2),

                                Section::make('Open Graph & Social')
                                    ->description('Controls how your site appears when shared on social media.')
                                    ->schema([
                                        TextInput::make('seo_og_image')
                                            ->label('Default OG Image URL')
                                            ->url()
                                            ->placeholder('https://yoursite.com/images/og-default.jpg')
                                            ->helperText('Recommended: 1200×630px. Used when no page-specific image is available.'),
                                        Select::make('seo_og_type')
                                            ->label('Default OG Type')
                                            ->options([
                                                'website' => 'website',
                                                'video.other' => 'video.other',
                                            ])
                                            ->default('website'),
                                        Select::make('seo_twitter_card')
                                            ->label('Twitter Card Type')
                                            ->options([
                                                'summary' => 'Summary',
                                                'summary_large_image' => 'Summary with Large Image',
                                                'player' => 'Player (for video embeds)',
                                            ])
                                            ->default('summary_large_image'),
                                        TextInput::make('seo_twitter_site')
                                            ->label('Twitter @username')
                                            ->placeholder('@yoursitehandle')
                                            ->helperText('Your site\'s Twitter handle for attribution.'),
                                        TextInput::make('seo_locale')
                                            ->label('OG Locale')
                                            ->placeholder('en_US')
                                            ->helperText('Language/region code (e.g. en_US, de_DE, ja_JP).'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Search Engines')
                            ->icon('heroicon-o-check-badge')
                            ->schema([
                                Section::make('Verification Codes')
                                    ->description('Paste the verification meta tag content value from each search engine\'s webmaster tools.')
                                    ->schema([
                                        TextInput::make('seo_google_verification')
                                            ->label('Google Search Console')
                                            ->placeholder('google-site-verification content value')
                                            ->helperText('From Google Search Console → Settings → Ownership verification → HTML tag.'),
                                        TextInput::make('seo_bing_verification')
                                            ->label('Bing Webmaster Tools')
                                            ->placeholder('msvalidate.01 content value')
                                            ->helperText('From Bing Webmaster Tools → Settings → Ownership verification.'),
                                        TextInput::make('seo_yandex_verification')
                                            ->label('Yandex Webmaster')
                                            ->placeholder('yandex-verification content value')
                                            ->helperText('From Yandex Webmaster → Settings → Verification.'),
                                        TextInput::make('seo_pinterest_verification')
                                            ->label('Pinterest')
                                            ->placeholder('p:domain_verify content value'),
                                    ])->columns(2),

                                Section::make('Robots & Indexing')
                                    ->schema([
                                        Textarea::make('seo_robots_txt')
                                            ->label('robots.txt Content')
                                            ->rows(12)
                                            ->helperText('Controls which pages search engines can crawl. Be careful editing this.'),
                                        Toggle::make('seo_noindex_private_videos')
                                            ->label('Noindex Private/Unlisted Videos')
                                            ->helperText('Add noindex to private and unlisted video pages.'),
                                        Toggle::make('seo_noindex_user_pages')
                                            ->label('Noindex User Settings/Wallet Pages')
                                            ->helperText('Prevent indexing of user account pages.'),
                                        Toggle::make('seo_canonical_enabled')
                                            ->label('Enable Canonical URLs')
                                            ->helperText('Adds <link rel="canonical"> to prevent duplicate content issues.'),
                                        Toggle::make('seo_force_trailing_slash')
                                            ->label('Force Trailing Slash')
                                            ->helperText('Append trailing slash to canonical URLs (not recommended for most sites).'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Video SEO')
                            ->icon('heroicon-o-play')
                            ->schema([
                                Placeholder::make('video_seo_info')
                                    ->content(new HtmlString(
                                        '<div class="text-sm p-3 rounded-lg" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3);">' .
                                        '<strong>📺 Video SEO Best Practices</strong><br>' .
                                        'HubTube automatically generates <strong>JSON-LD VideoObject</strong> schema markup for every video page. ' .
                                        'This tells Google, Bing, and Yandex about your video\'s thumbnail, duration, upload date, and description — ' .
                                        'enabling <strong>rich video snippets</strong> in search results (thumbnail + duration badge).<br><br>' .
                                        '<strong>Available template variables:</strong> <code>{title}</code>, <code>{description}</code>, <code>{site_name}</code>, ' .
                                        '<code>{uploader}</code>, <code>{category}</code>, <code>{duration}</code>, <code>{views}</code>, <code>{tags}</code>' .
                                        '</div>'
                                    )),

                                Section::make('Video Schema Markup')
                                    ->schema([
                                        Toggle::make('seo_video_schema_enabled')
                                            ->label('Enable VideoObject JSON-LD Schema')
                                            ->helperText('Generates structured data for Google/Bing/Yandex video rich results.')
                                            ->default(true),
                                        Toggle::make('seo_video_embed_enabled')
                                            ->label('Include Embed URL in Schema')
                                            ->helperText('Tells search engines the video can be embedded. Improves rich result eligibility.'),
                                    ])->columns(2),

                                Section::make('Video Title & Description Templates')
                                    ->schema([
                                        TextInput::make('seo_video_title_template')
                                            ->label('Video Page Title Template')
                                            ->placeholder('{title} | {site_name}')
                                            ->helperText('Template for the <title> tag on video pages. Max ~60 chars recommended.'),
                                        Textarea::make('seo_video_description_template')
                                            ->label('Video Meta Description Template')
                                            ->rows(2)
                                            ->placeholder('{description}')
                                            ->helperText('Uses the video\'s description. Truncated to 160 chars automatically.'),
                                        Toggle::make('seo_video_auto_description')
                                            ->label('Auto-Generate Description if Empty')
                                            ->helperText('If a video has no description, generate one from the template below.'),
                                        Textarea::make('seo_video_description_fallback')
                                            ->label('Fallback Description Template')
                                            ->rows(2)
                                            ->placeholder('Watch {title} on {site_name}. {category} video uploaded by {uploader}.')
                                            ->helperText('Used when a video has no description and auto-generate is enabled.'),
                                    ])->columns(2),

                                Section::make('Thumbnail SEO')
                                    ->description('Controls how thumbnails are presented to search engines.')
                                    ->schema([
                                        TextInput::make('seo_video_thumbnail_alt_template')
                                            ->label('Thumbnail Alt Text Template')
                                            ->placeholder('{title} - Video Thumbnail')
                                            ->helperText('Alt text for video thumbnails. Critical for image SEO and accessibility.'),
                                    ]),
                            ]),

                        Tabs\Tab::make('Page Templates')
                            ->icon('heroicon-o-document-text')
                            ->schema([
                                Section::make('Homepage')
                                    ->schema([
                                        TextInput::make('seo_home_title')
                                            ->label('Homepage Title')
                                            ->placeholder('Leave empty to use Default Site Title')
                                            ->maxLength(70),
                                        Textarea::make('seo_home_description')
                                            ->label('Homepage Description')
                                            ->rows(2)
                                            ->maxLength(160),
                                    ])->columns(2),

                                Section::make('Channel Pages')
                                    ->schema([
                                        TextInput::make('seo_channel_title_template')
                                            ->label('Channel Title Template')
                                            ->placeholder('{channel_name} | {site_name}'),
                                        Textarea::make('seo_channel_description_template')
                                            ->label('Channel Description Template')
                                            ->rows(2)
                                            ->placeholder('Watch videos from {channel_name} on {site_name}.'),
                                    ])->columns(2),

                                Section::make('Category Pages')
                                    ->schema([
                                        TextInput::make('seo_category_title_template')
                                            ->label('Category Title Template')
                                            ->placeholder('{category_name} Videos | {site_name}'),
                                        Textarea::make('seo_category_description_template')
                                            ->label('Category Description Template')
                                            ->rows(2)
                                            ->placeholder('Browse {category_name} videos on {site_name}.'),
                                    ])->columns(2),

                                Section::make('Other Pages')
                                    ->schema([
                                        TextInput::make('seo_trending_title')
                                            ->label('Trending Page Title'),
                                        Textarea::make('seo_trending_description')
                                            ->label('Trending Page Description')
                                            ->rows(2),
                                        TextInput::make('seo_shorts_title')
                                            ->label('Shorts Page Title'),
                                        Textarea::make('seo_shorts_description')
                                            ->label('Shorts Page Description')
                                            ->rows(2),
                                        TextInput::make('seo_live_title')
                                            ->label('Live Streams Page Title'),
                                        Textarea::make('seo_live_description')
                                            ->label('Live Streams Page Description')
                                            ->rows(2),
                                        TextInput::make('seo_search_title')
                                            ->label('Search Results Title Template')
                                            ->helperText('Use {query} for the search term.'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Structured Data')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Placeholder::make('schema_info')
                                    ->content(new HtmlString(
                                        '<div class="text-sm p-3 rounded-lg" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3);">' .
                                        '<strong>🏢 Organization Schema</strong><br>' .
                                        'Defines your site as an organization in Google\'s Knowledge Graph. ' .
                                        'Fill in your organization details and social profiles to enable a branded knowledge panel.' .
                                        '</div>'
                                    )),

                                Section::make('Organization Schema')
                                    ->schema([
                                        Toggle::make('seo_schema_enabled')
                                            ->label('Enable Organization Schema')
                                            ->helperText('Adds Organization JSON-LD to the homepage.'),
                                        TextInput::make('seo_schema_org_name')
                                            ->label('Organization Name')
                                            ->placeholder('Your Site Name'),
                                        TextInput::make('seo_schema_org_logo')
                                            ->label('Organization Logo URL')
                                            ->url()
                                            ->placeholder('https://yoursite.com/logo.png')
                                            ->helperText('Recommended: 112×112px minimum, square.'),
                                        TextInput::make('seo_schema_org_url')
                                            ->label('Organization URL')
                                            ->url()
                                            ->placeholder('https://yoursite.com'),
                                        Textarea::make('seo_schema_same_as')
                                            ->label('Social Profile URLs (one per line)')
                                            ->rows(4)
                                            ->placeholder("https://twitter.com/yoursite\nhttps://facebook.com/yoursite\nhttps://instagram.com/yoursite")
                                            ->helperText('Used for sameAs property in Organization schema.'),
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('Sitemap')
                            ->icon('heroicon-o-map')
                            ->schema([
                                Section::make('Video Sitemap')
                                    ->description('Enhanced sitemap with video-specific metadata for Google Video Search.')
                                    ->schema([
                                        Toggle::make('seo_sitemap_video_enabled')
                                            ->label('Enable Video Sitemap Extensions')
                                            ->helperText('Adds video:video namespace with thumbnail, duration, description, view count, and tags to sitemap entries.'),
                                        TextInput::make('seo_sitemap_max_videos')
                                            ->label('Max Videos in Sitemap')
                                            ->numeric()
                                            ->minValue(100)
                                            ->maxValue(50000)
                                            ->default(10000)
                                            ->helperText('Google supports up to 50,000 URLs per sitemap.'),
                                        TextInput::make('seo_sitemap_max_channels')
                                            ->label('Max Channels in Sitemap')
                                            ->numeric()
                                            ->minValue(100)
                                            ->maxValue(50000)
                                            ->default(5000),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $type = match (true) {
                is_bool($value) => 'boolean',
                is_int($value) => 'integer',
                is_array($value) => 'array',
                default => 'string',
            };

            Setting::set($key, $value, 'seo', $type);
        }

        Notification::make()
            ->title('SEO settings saved successfully')
            ->success()
            ->send();
    }
}
