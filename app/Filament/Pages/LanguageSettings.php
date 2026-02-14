<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AdminLogger;
use App\Services\TranslationService;
use App\Models\TranslationOverride;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class LanguageSettings extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-language';
    protected static ?string $navigationLabel = 'Languages';
    protected static ?string $navigationGroup = 'Appearance';
    protected static ?int $navigationSort = 8;
    protected static string $view = 'filament.pages.language-settings';

    public ?array $data = [];

    public string $generationOutput = '';
    public bool $regenerating = false;
    public string $regenerationStatus = '';
    public string $regenerationStep = '';
    public bool $useForceMode = true;

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

    // ── Translation Overrides Table ──

    protected function getLocaleOptions(): array
    {
        $options = ['*' => 'All Languages'];
        $enabledLocales = $this->getEnabledLocalesList();
        foreach ($enabledLocales as $code) {
            $lang = TranslationService::LANGUAGES[$code] ?? null;
            if ($lang) {
                $options[$code] = "{$lang['flag']} {$lang['native']}";
            }
        }
        return $options;
    }

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

    public function table(Table $table): Table
    {
        return $table
            ->query(TranslationOverride::query())
            ->heading('Translation Overrides')
            ->description('Fix or replace words/phrases that Google Translate gets wrong. Overrides apply to both dynamic content and static UI translations.')
            ->defaultSort('locale')
            ->columns([
                Tables\Columns\TextColumn::make('locale')
                    ->label('Language')
                    ->formatStateUsing(function (string $state) {
                        if ($state === '*') return 'All';
                        $lang = TranslationService::LANGUAGES[$state] ?? null;
                        return $lang ? "{$lang['flag']} {$lang['native']}" : $state;
                    })
                    ->badge()
                    ->color(fn (string $state) => $state === '*' ? 'purple' : 'gray'),

                Tables\Columns\TextColumn::make('original_text')
                    ->label('Wrong Text')
                    ->color('danger')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('replacement_text')
                    ->label('Correct Text')
                    ->color('success')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('notes')
                    ->color('gray')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('locale')
                    ->label('Language')
                    ->options(fn () => $this->getLocaleOptions()),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form(fn () => $this->overrideFormSchema())
                    ->mutateRecordDataUsing(function (array $data): array {
                        $data['case_sensitive'] = (bool) ($data['case_sensitive'] ?? false);
                        return $data;
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Override')
                    ->model(TranslationOverride::class)
                    ->form(fn () => $this->overrideFormSchema())
                    ->using(function (array $data): TranslationOverride {
                        $exists = TranslationOverride::where('locale', $data['locale'])
                            ->where('original_text', $data['original_text'])
                            ->exists();

                        if ($exists) {
                            Notification::make()
                                ->title('An override for this word/phrase already exists for this language')
                                ->warning()
                                ->send();
                            return new TranslationOverride();
                        }

                        return TranslationOverride::create($data);
                    }),

                Tables\Actions\Action::make('clearCache')
                    ->label('Clear Translation Cache')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('This will delete ALL cached translations. They will be re-translated (with overrides applied) on next request.')
                    ->action(function () {
                        $this->clearTranslationCache();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('No translation overrides')
            ->emptyStateDescription('Add overrides to fix words that Google Translate gets wrong.')
            ->emptyStateIcon('heroicon-o-language')
            ->striped();
    }

    protected function overrideFormSchema(): array
    {
        return [
            Select::make('locale')
                ->label('Language')
                ->options(fn () => $this->getLocaleOptions())
                ->default('*')
                ->required()
                ->helperText('"All Languages" applies to every locale'),

            TextInput::make('original_text')
                ->label('Wrong Word/Phrase')
                ->required()
                ->placeholder('e.g. wrong translation')
                ->helperText('The incorrect text that appears after translation'),

            TextInput::make('replacement_text')
                ->label('Correct Replacement')
                ->required()
                ->placeholder('e.g. correct word')
                ->helperText('What it should say instead'),

            TextInput::make('notes')
                ->label('Notes')
                ->placeholder('e.g. Slang term, keep in English')
                ->helperText('Optional notes for your reference'),

            Checkbox::make('case_sensitive')
                ->label('Case-sensitive match'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ];
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
