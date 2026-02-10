<?php

namespace App\Filament\Pages;

use App\Services\EmbedScraperService;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Livewire\Attributes\Url;

class VideoEmbedder extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-film';
    protected static ?string $navigationLabel = 'Video Embedder';
    protected static ?string $navigationGroup = 'Content';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.pages.video-embedder';

    #[Url]
    public ?string $selectedSite = 'eporner';
    
    #[Url]
    public ?string $searchQuery = '';
    
    public int $currentPage = 1;
    public int $pagesToFetch = 1;
    public array $searchResults = [];
    public array $selectedVideos = [];
    public bool $isLoading = false;
    public bool $isLoadingMore = false;
    public bool $hasNextPage = false;
    public bool $hasPrevPage = false;
    public int $totalLoaded = 0;
    public ?string $errorMessage = null;
    public ?string $errorSuggestion = null;
    public bool $isBlocked = false;

    protected EmbedScraperService $scraperService;

    public function boot(EmbedScraperService $scraperService): void
    {
        $this->scraperService = $scraperService;
    }

    public function mount(): void
    {
        $this->selectedVideos = [];
        $this->searchResults = [];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('selectedSite')
                    ->label('Source Site')
                    ->options([
                        'eporner' => '⭐ Eporner (API - Recommended)',
                        'redtube_api' => '⭐ RedTube (API)',
                        'xvideos' => 'XVideos (Scraper)',
                        'pornhub' => 'PornHub (Scraper)',
                        'xhamster' => 'xHamster (Scraper)',
                        'xnxx' => 'XNXX (Scraper)',
                        'youporn' => 'YouPorn (Scraper)',
                    ])
                    ->default('eporner')
                    ->helperText('⭐ API sites work directly — no Node.js scraper needed, no geo-blocking.')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetSearch()),
                TextInput::make('searchQuery')
                    ->label('Search Keywords')
                    ->placeholder('Enter keywords to search...')
                    ->live(debounce: 500),
            ])
            ->columns(2);
    }

    public function search(): void
    {
        if (empty($this->searchQuery)) {
            Notification::make()
                ->title('Please enter search keywords')
                ->warning()
                ->send();
            return;
        }

        $this->isLoading = true;
        $this->errorMessage = null;
        $this->currentPage = 1;
        $this->selectedVideos = [];
        $this->searchResults = [];
        $this->totalLoaded = 0;
        
        $this->fetchResults(false);
    }

    public function loadMore(): void
    {
        if (!$this->hasNextPage) return;
        $this->currentPage++;
        $this->isLoadingMore = true;
        $this->fetchResults(true);
    }

    protected function fetchResults(bool $append = false): void
    {
        if (!$append) {
            $this->isLoading = true;
        }
        $this->errorMessage = null;
        $this->errorSuggestion = null;
        $this->isBlocked = false;
        
        if ($this->pagesToFetch > 1) {
            $toPage = $this->currentPage + $this->pagesToFetch - 1;
            $results = $this->scraperService->searchMultiPage(
                $this->selectedSite,
                $this->searchQuery,
                $this->currentPage,
                $toPage
            );
            // Advance currentPage to the last fetched page
            if (!isset($results['error'])) {
                $this->currentPage = $results['lastPage'] ?? $this->currentPage;
            }
        } else {
            $results = $this->scraperService->search(
                $this->selectedSite,
                $this->searchQuery,
                $this->currentPage
            );
        }

        $this->isLoading = false;
        $this->isLoadingMore = false;

        if (isset($results['error'])) {
            $this->errorMessage = $results['message'] ?? $results['error'];
            $this->errorSuggestion = $results['suggestion'] ?? null;
            $this->isBlocked = $results['blocked'] ?? false;
            if (!$append) {
                $this->searchResults = [];
                $this->totalLoaded = 0;
            }
            return;
        }

        $newVideos = $results['videos'] ?? [];

        if ($append) {
            // Deduplicate by sourceId
            $existingIds = array_column($this->searchResults, 'sourceId');
            foreach ($newVideos as $video) {
                if (!in_array($video['sourceId'], $existingIds)) {
                    $this->searchResults[] = $video;
                }
            }
        } else {
            $this->searchResults = $newVideos;
        }

        $this->totalLoaded = count($this->searchResults);
        $this->hasNextPage = $results['hasNextPage'] ?? false;
        $this->hasPrevPage = false; // Not used in load-more mode
    }

    protected function resetSearch(): void
    {
        $this->searchResults = [];
        $this->selectedVideos = [];
        $this->currentPage = 1;
        $this->errorMessage = null;
    }

    public function toggleVideoSelection(string $sourceId): void
    {
        if (in_array($sourceId, $this->selectedVideos)) {
            $this->selectedVideos = array_values(array_diff($this->selectedVideos, [$sourceId]));
        } else {
            $this->selectedVideos[] = $sourceId;
        }
    }

    public function selectAll(): void
    {
        $this->selectedVideos = [];
        foreach ($this->searchResults as $video) {
            if (!($video['isImported'] ?? false)) {
                $this->selectedVideos[] = $video['sourceId'];
            }
        }
    }

    public function deselectAll(): void
    {
        $this->selectedVideos = [];
    }

    public function importSelected(): void
    {
        if (empty($this->selectedVideos)) {
            Notification::make()
                ->title('No videos selected')
                ->warning()
                ->send();
            return;
        }

        $videosToImport = array_filter($this->searchResults, function ($video) {
            return in_array($video['sourceId'], $this->selectedVideos);
        });

        $result = $this->scraperService->bulkImport(array_values($videosToImport));

        if ($result['imported'] > 0) {
            Notification::make()
                ->title('Import Complete')
                ->body("Imported: {$result['imported']}, Skipped: {$result['skipped']}. Videos are now live on the site.")
                ->success()
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('View Videos')
                        ->url(route('filament.admin.resources.videos.index'))
                ])
                ->send();
        } else {
            $errorMsg = '';
            if (!empty($result['errors'])) {
                $errorMsg = ' Errors: ' . collect($result['errors'])->pluck('error')->implode(', ');
            }
            Notification::make()
                ->title('Import Failed')
                ->body("No videos were imported. Skipped: {$result['skipped']}.{$errorMsg}")
                ->warning()
                ->send();
        }

        // Refresh results to update imported status
        $this->fetchResults();
        $this->selectedVideos = [];
    }

    public function getSelectedCount(): int
    {
        return count($this->selectedVideos);
    }
}
