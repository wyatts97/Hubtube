<?php

namespace App\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Section;
use App\Models\SearchIndexSubmission;
use App\Models\Setting;
use App\Models\Video;
use App\Services\AdminLogger;
use App\Services\IndexNowService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\HtmlString;

class SearchIndexingSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-magnifying-glass-circle';
    protected static ?string $navigationLabel = 'Search Indexing';
    protected static string | \UnitEnum | null $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 5;
    protected string $view = 'filament.pages.site-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'indexnow_enabled' => Setting::get('indexnow_enabled', false),
            'indexnow_key' => Setting::get('indexnow_key', ''),
            'indexnow_key_location' => Setting::get('indexnow_key_location', ''),
            'indexnow_endpoint' => Setting::get('indexnow_endpoint', IndexNowService::DEFAULT_ENDPOINT),
            'indexnow_auto_submit_videos' => Setting::get('indexnow_auto_submit_videos', true),
            'indexnow_submit_translated_urls' => Setting::get('indexnow_submit_translated_urls', true),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Search Indexing')
                    ->tabs([
                        Tab::make('IndexNow')
                            ->icon('heroicon-o-bolt')
                            ->schema([
                                Placeholder::make('indexnow_intro')
                                    ->content(new HtmlString(
                                        '<div class="text-sm p-3 rounded-lg" style="background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.3);">'
                                        . '<strong>⚡ IndexNow</strong> notifies <strong>Bing, Yandex, Seznam, Naver</strong> and other compatible search engines '
                                        . 'the moment a new public video is published — without waiting for a sitemap recrawl.'
                                        . '<br><br><strong>Note:</strong> Google does <em>not</em> participate in IndexNow for general video pages. '
                                        . 'Submit your sitemap to Google Search Console for Google indexing.'
                                        . '</div>'
                                    )),

                                Section::make('IndexNow Configuration')
                                    ->schema([
                                        Toggle::make('indexnow_enabled')
                                            ->label('Enable IndexNow')
                                            ->helperText('Master toggle. When off, no submissions are sent and the key file returns 404.'),
                                        TextInput::make('indexnow_key')
                                            ->label('IndexNow Key')
                                            ->placeholder('Click "Generate Key" to create one')
                                            ->helperText('A 8–128 character alphanumeric string. Search engines fetch this at /{key}.txt to verify ownership.')
                                            ->regex('/^[A-Za-z0-9\-]{8,128}$/')
                                            ->maxLength(128),
                                        TextInput::make('indexnow_key_location')
                                            ->label('Custom Key Location URL (optional)')
                                            ->url()
                                            ->placeholder('Leave blank to auto-use /{key}.txt')
                                            ->helperText('Override only if you host the key file at a non-default path.'),
                                        TextInput::make('indexnow_endpoint')
                                            ->label('IndexNow Endpoint')
                                            ->url()
                                            ->default(IndexNowService::DEFAULT_ENDPOINT)
                                            ->helperText('The default api.indexnow.org endpoint forwards to all participating engines.'),
                                    ])->columns(2),

                                Section::make('Automation')
                                    ->schema([
                                        Toggle::make('indexnow_auto_submit_videos')
                                            ->label('Auto-submit new videos')
                                            ->helperText('Submit a video URL automatically when it is published, approved, and public.')
                                            ->default(true),
                                        Toggle::make('indexnow_submit_translated_urls')
                                            ->label('Include translated alternate URLs')
                                            ->helperText('Also submit /{locale}/{translated-slug} URLs for each enabled language.')
                                            ->default(true),
                                    ])->columns(2),

                                Section::make('Verification')
                                    ->schema([
                                        Placeholder::make('indexnow_key_url')
                                            ->label('Key Verification URL')
                                            ->content(function () {
                                                $key = (string) Setting::get('indexnow_key', '');
                                                if ($key === '') {
                                                    return new HtmlString('<span class="text-sm text-gray-400">Save a key first.</span>');
                                                }
                                                $url = url('/' . $key . '.txt');
                                                return new HtmlString(
                                                    '<a href="' . e($url) . '" target="_blank" class="text-primary-500 underline font-mono text-sm">' . e($url) . '</a>'
                                                );
                                            }),
                                        Placeholder::make('indexnow_recent')
                                            ->label('Recent Activity')
                                            ->content(function () {
                                                $total = SearchIndexSubmission::where('engine', 'indexnow')->count();
                                                $success = SearchIndexSubmission::where('engine', 'indexnow')->where('status', 'success')->count();
                                                $failed = SearchIndexSubmission::where('engine', 'indexnow')->where('status', 'failed')->count();
                                                $last = SearchIndexSubmission::where('engine', 'indexnow')
                                                    ->whereNotNull('submitted_at')
                                                    ->orderByDesc('submitted_at')
                                                    ->first();
                                                $lastTxt = $last?->submitted_at?->diffForHumans() ?? 'never';
                                                return new HtmlString(
                                                    '<div class="text-sm space-y-1">'
                                                    . '<div>Total submissions: <strong>' . $total . '</strong></div>'
                                                    . '<div>Successful: <strong class="text-green-500">' . $success . '</strong></div>'
                                                    . '<div>Failed: <strong class="text-red-500">' . $failed . '</strong></div>'
                                                    . '<div>Last submission: <strong>' . e($lastTxt) . '</strong></div>'
                                                    . '</div>'
                                                );
                                            }),
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

        AdminLogger::settingsSaved('Search Indexing', array_keys($data));

        Notification::make()
            ->title('Search indexing settings saved')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateKey')
                ->label('Generate Key')
                ->icon('heroicon-o-key')
                ->color('gray')
                ->action(function () {
                    $key = IndexNowService::generateKey();
                    $this->data['indexnow_key'] = $key;
                    Notification::make()
                        ->title('New key generated')
                        ->body('Save the form to persist it.')
                        ->success()
                        ->send();
                }),
            Action::make('submitRecent')
                ->label('Submit Recent Videos')
                ->icon('heroicon-o-paper-airplane')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Submit the last 50 public videos published in the past 7 days to IndexNow.')
                ->action(function () {
                    $service = app(IndexNowService::class);
                    if (!$service->isEnabled()) {
                        Notification::make()
                            ->title('IndexNow is not enabled')
                            ->danger()
                            ->send();
                        return;
                    }

                    $videos = Video::query()
                        ->where('status', 'processed')
                        ->where('is_approved', true)
                        ->where('privacy', 'public')
                        ->whereNotNull('published_at')
                        ->where('published_at', '>=', now()->subDays(7))
                        ->whereNull('queue_order')
                        ->orderByDesc('published_at')
                        ->limit(50)
                        ->get(['id', 'slug']);

                    if ($videos->isEmpty()) {
                        Notification::make()
                            ->title('No eligible recent videos found')
                            ->warning()
                            ->send();
                        return;
                    }

                    $urls = $videos->map(fn ($v) => url("/{$v->slug}"))->all();
                    $ok = $service->submitUrls($urls);

                    Notification::make()
                        ->title($ok ? 'Submission accepted' : 'Submission failed')
                        ->body(count($urls) . ' URL(s) submitted')
                        ->{$ok ? 'success' : 'danger'}()
                        ->send();
                }),
        ];
    }
}
