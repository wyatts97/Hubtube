<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
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
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 8;
    protected static string $view = 'filament.pages.language-settings';

    public const LANGUAGES = [
        'en' => ['name' => 'English', 'native' => 'English', 'flag' => "\u{1F1FA}\u{1F1F8}"],
        'es' => ['name' => 'Spanish', 'native' => 'Espa\u{00F1}ol', 'flag' => "\u{1F1EA}\u{1F1F8}"],
        'fr' => ['name' => 'French', 'native' => 'Fran\u{00E7}ais', 'flag' => "\u{1F1EB}\u{1F1F7}"],
        'de' => ['name' => 'German', 'native' => 'Deutsch', 'flag' => "\u{1F1E9}\u{1F1EA}"],
        'pt' => ['name' => 'Portuguese', 'native' => 'Portugu\u{00EA}s', 'flag' => "\u{1F1E7}\u{1F1F7}"],
        'it' => ['name' => 'Italian', 'native' => 'Italiano', 'flag' => "\u{1F1EE}\u{1F1F9}"],
        'nl' => ['name' => 'Dutch', 'native' => 'Nederlands', 'flag' => "\u{1F1F3}\u{1F1F1}"],
        'ru' => ['name' => 'Russian', 'native' => 'Russian', 'flag' => "\u{1F1F7}\u{1F1FA}"],
        'ja' => ['name' => 'Japanese', 'native' => 'Japanese', 'flag' => "\u{1F1EF}\u{1F1F5}"],
        'ko' => ['name' => 'Korean', 'native' => 'Korean', 'flag' => "\u{1F1F0}\u{1F1F7}"],
        'zh' => ['name' => 'Chinese', 'native' => 'Chinese', 'flag' => "\u{1F1E8}\u{1F1F3}"],
        'ar' => ['name' => 'Arabic', 'native' => 'Arabic', 'flag' => "\u{1F1F8}\u{1F1E6}"],
        'hi' => ['name' => 'Hindi', 'native' => 'Hindi', 'flag' => "\u{1F1EE}\u{1F1F3}"],
        'tr' => ['name' => 'Turkish', 'native' => 'T\u{00FC}rk\u{00E7}e', 'flag' => "\u{1F1F9}\u{1F1F7}"],
        'pl' => ['name' => 'Polish', 'native' => 'Polski', 'flag' => "\u{1F1F5}\u{1F1F1}"],
        'sv' => ['name' => 'Swedish', 'native' => 'Svenska', 'flag' => "\u{1F1F8}\u{1F1EA}"],
        'da' => ['name' => 'Danish', 'native' => 'Dansk', 'flag' => "\u{1F1E9}\u{1F1F0}"],
        'no' => ['name' => 'Norwegian', 'native' => 'Norsk', 'flag' => "\u{1F1F3}\u{1F1F4}"],
        'fi' => ['name' => 'Finnish', 'native' => 'Suomi', 'flag' => "\u{1F1EB}\u{1F1EE}"],
        'cs' => ['name' => 'Czech', 'native' => 'Czech', 'flag' => "\u{1F1E8}\u{1F1FF}"],
        'th' => ['name' => 'Thai', 'native' => 'Thai', 'flag' => "\u{1F1F9}\u{1F1ED}"],
        'vi' => ['name' => 'Vietnamese', 'native' => 'Vietnamese', 'flag' => "\u{1F1FB}\u{1F1F3}"],
        'id' => ['name' => 'Indonesian', 'native' => 'Bahasa Indonesia', 'flag' => "\u{1F1EE}\u{1F1E9}"],
        'ms' => ['name' => 'Malay', 'native' => 'Bahasa Melayu', 'flag' => "\u{1F1F2}\u{1F1FE}"],
        'ro' => ['name' => 'Romanian', 'native' => 'Romanian', 'flag' => "\u{1F1F7}\u{1F1F4}"],
        'uk' => ['name' => 'Ukrainian', 'native' => 'Ukrainian', 'flag' => "\u{1F1FA}\u{1F1E6}"],
        'el' => ['name' => 'Greek', 'native' => 'Greek', 'flag' => "\u{1F1EC}\u{1F1F7}"],
        'hu' => ['name' => 'Hungarian', 'native' => 'Magyar', 'flag' => "\u{1F1ED}\u{1F1FA}"],
        'he' => ['name' => 'Hebrew', 'native' => 'Hebrew', 'flag' => "\u{1F1EE}\u{1F1F1}"],
        'bg' => ['name' => 'Bulgarian', 'native' => 'Bulgarian', 'flag' => "\u{1F1E7}\u{1F1EC}"],
        'hr' => ['name' => 'Croatian', 'native' => 'Hrvatski', 'flag' => "\u{1F1ED}\u{1F1F7}"],
        'sk' => ['name' => 'Slovak', 'native' => 'Slovak', 'flag' => "\u{1F1F8}\u{1F1F0}"],
        'sr' => ['name' => 'Serbian', 'native' => 'Serbian', 'flag' => "\u{1F1F7}\u{1F1F8}"],
        'lt' => ['name' => 'Lithuanian', 'native' => 'Lithuanian', 'flag' => "\u{1F1F1}\u{1F1F9}"],
        'lv' => ['name' => 'Latvian', 'native' => 'Latvian', 'flag' => "\u{1F1F1}\u{1F1FB}"],
        'et' => ['name' => 'Estonian', 'native' => 'Eesti', 'flag' => "\u{1F1EA}\u{1F1EA}"],
        'fil' => ['name' => 'Filipino', 'native' => 'Filipino', 'flag' => "\u{1F1F5}\u{1F1ED}"],
    ];

    public ?array $data = [];

    // Override form fields
    public string $overrideLocale = '*';
    public string $overrideOriginal = '';
    public string $overrideReplacement = '';
    public bool $overrideCaseSensitive = false;
    public string $overrideNotes = '';
    public ?int $editingOverrideId = null;
    public string $overrideFilterLocale = '';

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
        foreach (self::LANGUAGES as $code => $lang) {
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

    // --- Translation Overrides ---

    protected function getEnabledLocalesList(): array
    {
        if (!(bool) Setting::get('translation_enabled', false)) {
            return [Setting::get('default_language', 'en')];
        }

        $enabled = Setting::get('enabled_languages');
        if (is_string($enabled)) {
            $enabled = json_decode($enabled, true);
        }

        return is_array($enabled) && count($enabled) > 0 ? $enabled : [Setting::get('default_language', 'en')];
    }

    public function getOverridesProperty()
    {
        try {
            $query = DB::table('translation_overrides')->orderBy('locale')->orderBy('original_text');

            if ($this->overrideFilterLocale) {
                $query->where('locale', $this->overrideFilterLocale);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect();
        }
    }

    public function getLocaleOptionsProperty(): array
    {
        $options = ['*' => '🌐 All Languages'];
        $enabledLocales = $this->getEnabledLocalesList();
        foreach ($enabledLocales as $code) {
            $lang = self::LANGUAGES[$code] ?? null;
            if ($lang) {
                $options[$code] = "{$lang['flag']} {$lang['native']}";
            }
        }
        return $options;
    }

    public function saveOverride(): void
    {
        if (empty(trim($this->overrideOriginal)) || empty(trim($this->overrideReplacement))) {
            Notification::make()
                ->title('Both original and replacement text are required')
                ->danger()
                ->send();
            return;
        }

        try {
            $data = [
                'locale' => $this->overrideLocale,
                'original_text' => trim($this->overrideOriginal),
                'replacement_text' => trim($this->overrideReplacement),
                'case_sensitive' => $this->overrideCaseSensitive,
                'notes' => trim($this->overrideNotes) ?: null,
                'is_active' => true,
            ];

            if ($this->editingOverrideId) {
                DB::table('translation_overrides')
                    ->where('id', $this->editingOverrideId)
                    ->update($data);
            } else {
                $exists = DB::table('translation_overrides')
                    ->where('locale', $data['locale'])
                    ->where('original_text', $data['original_text'])
                    ->exists();

                if ($exists) {
                    Notification::make()
                        ->title('An override for this word/phrase already exists for this language')
                        ->warning()
                        ->send();
                    return;
                }

                DB::table('translation_overrides')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
            }

            $this->resetOverrideForm();

            Notification::make()
                ->title('Translation override saved')
                ->body('Re-run `php artisan translations:generate --force` to apply to static UI files.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving override')
                ->body('Run `php artisan migrate` first to create the translation tables.')
                ->danger()
                ->send();
        }
    }

    public function editOverride(int $id): void
    {
        $override = DB::table('translation_overrides')->find($id);
        if (!$override) return;

        $this->editingOverrideId = $id;
        $this->overrideLocale = $override->locale;
        $this->overrideOriginal = $override->original_text;
        $this->overrideReplacement = $override->replacement_text;
        $this->overrideCaseSensitive = (bool) $override->case_sensitive;
        $this->overrideNotes = $override->notes ?? '';
    }

    public function deleteOverride(int $id): void
    {
        DB::table('translation_overrides')->where('id', $id)->delete();

        Notification::make()
            ->title('Override deleted')
            ->success()
            ->send();
    }

    public function toggleOverride(int $id): void
    {
        $override = DB::table('translation_overrides')->find($id);
        if ($override) {
            DB::table('translation_overrides')
                ->where('id', $id)
                ->update(['is_active' => !$override->is_active]);
        }
    }

    public function resetOverrideForm(): void
    {
        $this->editingOverrideId = null;
        $this->overrideLocale = '*';
        $this->overrideOriginal = '';
        $this->overrideReplacement = '';
        $this->overrideCaseSensitive = false;
        $this->overrideNotes = '';
    }

    public function clearTranslationCache(): void
    {
        try {
            Cache::flush();
            DB::table('translations')->truncate();
        } catch (\Exception $e) {
            // Tables may not exist yet
        }

        Notification::make()
            ->title('Translation cache cleared')
            ->body('All cached translations have been purged. They will be re-translated on next request.')
            ->success()
            ->send();
    }
}
