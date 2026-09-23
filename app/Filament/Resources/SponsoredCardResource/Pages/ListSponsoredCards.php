<?php

namespace App\Filament\Resources\SponsoredCardResource\Pages;

use App\Filament\Resources\SponsoredCardResource;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSponsoredCards extends ListRecords
{
    protected static string $resource = SponsoredCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('frequency')
                ->label(fn () => 'Every '.Setting::get('sponsored_card_frequency', 8).' videos')
                ->icon('phosphor-sliders-horizontal')
                ->color('gray')
                ->modalHeading('Card frequency')
                ->modalWidth('sm')
                ->fillForm(fn () => ['frequency' => (int) Setting::get('sponsored_card_frequency', 8)])
                ->schema([
                    TextInput::make('frequency')
                        ->label('Show a card every')
                        ->suffix('videos')
                        ->numeric()
                        ->integer()
                        ->required()
                        ->minValue(2)
                        ->maxValue(50),
                ])
                ->action(function (array $data) {
                    Setting::set('sponsored_card_frequency', (int) $data['frequency'], 'ads', 'integer');
                    Notification::make()->title('Saved')->success()->send();
                }),
            CreateAction::make()->label('Add')->icon('phosphor-plus'),
        ];
    }
}
