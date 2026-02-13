<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use App\Services\TranslationService;
use Illuminate\Support\Facades\Artisan;
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

    public ?array $data = [];

    // Override form fields
    public string $overrideLocale = '*';
    public string $overrideOriginal = '';
    public string $overrideReplacement = '';
    public bool $overrideCaseSensitive = false;
    public string $overrideNotes = '';
    public ?int $editingOverrideId = null;
    public string $overrideFilterLocale = '';
    public string $generationOutput = '';
    public bool $regenerating = false;
    public string $regenerationStatus = '';
    public string $regenerationStep = ''; // 'generate', 'build', 'done'
    public bool $useForceMode = true; // true = full regenerate, false = sync new keys only

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

        AdminLogger::settingsSaved('Language', array_keys($data));

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
            $lang = TranslationService::LANGUAGES[$code] ?? null;
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

    /**
     * Full regenerate: re-translates everything from scratch (--force).
     */
    public function regenerateTranslations(): void
    {
        $this->useForceMode = true;
        $this->regenerating = true;
        $this->regenerationStep = 'generate';
        $this->regenerationStatus = 'Regenerating all translation files…';
        $this->generationOutput = '';
    }

    /**
     * Sync only: merges new/missing keys into existing locale files without overwriting.
     */
    public function syncTranslations(): void
    {
        $this->useForceMode = false;
        $this->regenerating = true;
        $this->regenerationStep = 'generate';
        $this->regenerationStatus = 'Syncing new translation keys…';
        $this->generationOutput = '';
    }

    /**
     * Called by Livewire polling to process the next step.
     */
    public function processRegeneration(): void
    {
        if (!$this->regenerating) {
            return;
        }

        if ($this->regenerationStep === 'generate') {
            try {
                $exitCode = Artisan::call('translations:generate', ['--force' => $this->useForceMode]);
                $output = Artisan::output();
                $this->generationOutput = trim($output);

                if ($exitCode === 0) {
                    $this->regenerationStep = 'build';
                    $this->regenerationStatus = 'Rebuilding frontend assets…';
                } else {
                    $this->regenerating = false;
                    $this->regenerationStep = '';
                    $this->regenerationStatus = '';
                    Notification::make()
                        ->title('Translation generation failed')
                        ->body('Check the output below for details.')
                        ->danger()
                        ->send();
                }
            } catch (\Exception $e) {
                $this->generationOutput = "Error: {$e->getMessage()}";
                $this->regenerating = false;
                $this->regenerationStep = '';
                $this->regenerationStatus = '';
                Notification::make()
                    ->title('Translation generation failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        } elseif ($this->regenerationStep === 'build') {
            try {
                $projectRoot = base_path();
                $npmPath = trim(shell_exec('which npm 2>/dev/null') ?? '');
                if (empty($npmPath)) {
                    $npmPath = '/usr/bin/npm';
                }

                $command = "cd {$projectRoot} && {$npmPath} run build 2>&1";
                $output = shell_exec($command);
                $buildOutput = trim($output ?? 'No output received.');
                $this->generationOutput .= "\n\n--- Build Output ---\n" . $buildOutput;

                $this->regenerating = false;
                $this->regenerationStep = '';
                $this->regenerationStatus = '';

                if (str_contains($buildOutput, 'built in') || str_contains($buildOutput, 'vite')) {
                    Notification::make()
                        ->title('Translations regenerated successfully')
                        ->body('All translation files have been generated and the frontend has been rebuilt.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Translations generated but build may have issues')
                        ->body('Check the output below for details.')
                        ->warning()
                        ->send();
                }
            } catch (\Exception $e) {
                $this->generationOutput .= "\n\nBuild Error: {$e->getMessage()}";
                $this->regenerating = false;
                $this->regenerationStep = '';
                $this->regenerationStatus = '';
                Notification::make()
                    ->title('Frontend rebuild failed')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();
            }
        }
    }
}
