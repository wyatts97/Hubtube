<?php

namespace App\Console\Commands;

use App\Models\Translation;
use App\Models\Video;
use App\Services\TranslationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Regenerate translated slugs for all existing translations.
 *
 * Useful after upgrading from naive Str::slug() to the unique slug generator,
 * or after enabling new languages where existing rows have empty/colliding slugs.
 */
class BackfillTranslatedSlugs extends Command
{
    protected $signature = 'translations:backfill-slugs
        {--locale= : Only process this locale}
        {--type=video : Model type: video|all}
        {--force : Regenerate even if a slug already exists}
        {--dry-run : Show what would be changed without writing}';

    protected $description = 'Regenerate translated slugs for video titles using the unique slug generator';

    public function handle(TranslationService $service): int
    {
        $localeFilter = $this->option('locale');
        $type = (string) $this->option('type');
        $force = (bool) $this->option('force');
        $dry = (bool) $this->option('dry-run');

        $modelClass = match ($type) {
            'video', 'all' => Video::class,
            default => null,
        };

        if (!$modelClass) {
            $this->error("Unsupported --type={$type}");
            return self::FAILURE;
        }

        $query = Translation::query()
            ->where('translatable_type', $modelClass)
            ->where('field', 'title');

        if ($localeFilter) {
            $query->where('locale', $localeFilter);
        }

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('translated_slug')->orWhere('translated_slug', '');
            });
        }

        $total = (clone $query)->count();
        if ($total === 0) {
            $this->info('No translations need slugs.');
            return self::SUCCESS;
        }

        $this->info("Processing {$total} translation row(s)" . ($dry ? ' [dry run]' : ''));
        $bar = $this->output->createProgressBar($total);
        $updated = 0;
        $skipped = 0;

        $query->orderBy('id')->chunkById(500, function ($rows) use (&$updated, &$skipped, $service, $modelClass, $dry, $bar) {
            foreach ($rows as $row) {
                $value = (string) $row->value;
                if (trim($value) === '') {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $original = null;
                if ($modelClass === Video::class) {
                    $original = Video::where('id', $row->translatable_id)->value('title');
                }

                $newSlug = $service->generateUniqueTranslatedSlug(
                    $modelClass,
                    (int) $row->translatable_id,
                    (string) $row->locale,
                    $value,
                    $original,
                );

                if ($newSlug !== $row->translated_slug) {
                    if (!$dry) {
                        $row->translated_slug = $newSlug;
                        $row->save();
                    }
                    $updated++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Updated: {$updated}");
        $this->info("Skipped (empty value): {$skipped}");
        if ($dry) {
            $this->warn('Dry run — no rows were modified.');
        }

        return self::SUCCESS;
    }
}
