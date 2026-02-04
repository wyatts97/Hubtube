<?php

namespace App\Filament\Pages;

use App\Models\EmbeddedVideo;
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
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.pages.video-embedder';

    #[Url]
    public ?string $selectedSite = 'xvideos';
    
    #[Url]
    public ?string $searchQuery = '';
    
    public int $currentPage = 1;
    public array $searchResults = [];
    public array $selectedVideos = [];
    public bool $isLoading = false;
    public bool $hasNextPage = false;
    public bool $hasPrevPage = false;
    public ?string $errorMessage = null;

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
                        'xvideos' => 'XVideos',
                        'pornhub' => 'PornHub',
                        'xhamster' => 'xHamster',
                        'xnxx' => 'XNXX',
                        'redtube' => 'RedTube',
                        'youporn' => 'YouPorn',
                    ])
                    ->default('xvideos')
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
        
        $this->fetchResults();
    }

    public function nextPage(): void
    {
        if ($this->hasNextPage) {
            $this->currentPage++;
            $this->fetchResults();
        }
    }

    public function prevPage(): void
    {
        if ($this->hasPrevPage && $this->currentPage > 1) {
            $this->currentPage--;
            $this->fetchResults();
        }
    }

    protected function fetchResults(): void
    {
        $this->isLoading = true;
        
        $results = $this->scraperService->search(
            $this->selectedSite,
            $this->searchQuery,
            $this->currentPage
        );

        $this->isLoading = false;

        if (isset($results['error'])) {
            $this->errorMessage = $results['message'] ?? $results['error'];
            $this->searchResults = [];
            return;
        }

        $this->searchResults = $results['videos'] ?? [];
        $this->hasNextPage = $results['hasNextPage'] ?? false;
        $this->hasPrevPage = $results['hasPrevPage'] ?? false;
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

        Notification::make()
            ->title('Import Complete')
            ->body("Imported: {$result['imported']}, Skipped: {$result['skipped']}")
            ->success()
            ->send();

        // Refresh results to update imported status
        $this->fetchResults();
        $this->selectedVideos = [];
    }

    public function getSelectedCount(): int
    {
        return count($this->selectedVideos);
    }
}
