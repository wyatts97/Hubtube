<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\TranslationService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LanguageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationLabel = 'Languages';
    protected static ?string $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 8;
    protected static string $view = 'filament.pages.language-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $enabledLanguages = Setting::get('enabled_languages');
        if (is_string($enabledLanguages)) {
            $enabledLanguages = json_decode($enabledLanguages, true);
        }

        $this->form->fill([
            'translation_enabled' => (bool) Setting::get('translation_enabled', false),
            'default_language' => Setting::get('default_language', 'en'),
            'enabled_languages' => $enabledLanguages ?: ['en'],
            'auto_translate_content' => (bool) Setting::get('auto_translate_content', true),
        ]);
    }

    public function form(Form $form): Form
    {
        $languageOptions = [];
        foreach (TranslationService::LANGUAGES as $code => $lang) {
            $languageOptions[$code] = "{$lang['flag']} {$lang['native']} ({$lang['name']})";
        }

        return $form
            ->schema([
                Section::make('Translation Settings')
                    ->description('Enable multi-language support for your site. When enabled, a language switcher appears in the sidebar and content is auto-translated.')
                    ->schema([
                        Toggle::make('translation_enabled')
                            ->label('Enable Translation System')
                            ->helperText('Turn on multi-language support site-wide'),

                        Select::make('default_language')
                            ->label('Default Language')
                            ->options($languageOptions)
                            ->searchable()
                            ->helperText('The primary language of your site content'),

                        Toggle::make('auto_translate_content')
                            ->label('Auto-Translate Dynamic Content')
                            ->helperText('Automatically translate video titles, descriptions, etc. when users switch languages. Uses Google Translate (free).'),
                    ]),

                Section::make('Enabled Languages')
                    ->description('Select which languages visitors can switch to. Each enabled language creates SEO-friendly URLs (e.g. /es/video-title, /fr/trending).')
                    ->schema([
                        CheckboxList::make('enabled_languages')
                            ->label('Available Languages')
                            ->options($languageOptions)
                            ->searchable()
                            ->columns(3)
                            ->helperText('After enabling new languages, run `php artisan translations:generate` to auto-generate UI translation files.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Ensure default language is always in enabled list
        $enabled = $data['enabled_languages'] ?? ['en'];
        $default = $data['default_language'] ?? 'en';
        if (!in_array($default, $enabled)) {
            $enabled[] = $default;
        }

        Setting::set('translation_enabled', $data['translation_enabled'] ?? false);
        Setting::set('default_language', $default);
        Setting::set('enabled_languages', json_encode(array_values($enabled)));
        Setting::set('auto_translate_content', $data['auto_translate_content'] ?? true);

        Notification::make()
            ->title('Language settings saved')
            ->success()
            ->send();
    }
}
