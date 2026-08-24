# LibreTranslate Provider + Scheduled Translation Runs

## Context

Content translation currently runs through `stichoza/google-translate-php` — an unofficial scraper of Google's free endpoint. It has no API key, no SLA, and no rate-limit contract. In production this bit hard: a runaway on-view dispatch loop hammered the endpoint until Google blocked the whole VPS, and it stayed blocked for over 24 hours. The `es.json` catalogue was also poisoned with English text by failed runs before that behaviour was fixed.

The goal is to make the translation engine a **configurable choice** rather than a hardcoded scraper, with LibreTranslate (self-hosted or paid) as the recommended option, and to move translation onto an **admin-controlled schedule** so nothing user-facing can ever trigger a provider call again.

Decisions already taken:

- **Scheduled-only.** All on-view dispatching is removed. Pages render whatever is already translated.
- **One provider at a time, no automatic fallback.** Silent failover to Google would recreate the exact outage this work exists to prevent.
- Scheduled runs cover video titles/descriptions, categories, CMS pages, tags, and the UI string catalogues.
- Schedule UI is preset frequency + time-of-day, not raw cron.

### The detail most likely to be got wrong

LibreTranslate uses **two different codes for Brazilian Portuguese**, and you need both:

| Where | Code |
|---|---|
| `LT_LOAD_ONLY` (Argos *package* name) | `pb` |
| `GET /languages`, and the `target`/`source` API parameters | `pt-BR` |

Plain `pt` is *European* Portuguese — a genuinely different model. Confirmed on a live instance: `LT_LOAD_ONLY=en,es,pb` downloads "English → Portuguese (Brazil)", after which `/languages` reports `en`, `es`, `pt-BR`.

**Always trust `GET /languages` for the value in the locale map.**

This app uses `pt` as its internal locale and already maps it to `pt-BR` in `TranslationService::toHreflang()` — i.e. it has **already committed to Brazilian Portuguese**. So the correct mapping is app `pt` → provider `pt-BR`, and a per-provider locale-code map is mandatory, not optional. The provider code must never leak into URLs, `translations.locale`, or i18n filenames — it exists only inside the outbound HTTP body.

---

## Stage 1 — Provider abstraction (behaviour unchanged)

New namespace `App\Services\Translation`.

**`Contracts/TranslationProvider.php`**
```php
public function key(): string;                 // 'google' | 'libretranslate'
public function label(): string;
public function translate(string $text, string $targetLocale, string $sourceLocale, string $format = 'text'): string;
public function translateBatch(array $texts, string $targetLocale, string $sourceLocale, string $format = 'text'): array; // keys preserved
public function providerCode(string $appLocale): string;
public function supportedCodes(): array;
public function testConnection(): array;       // ['success' => bool, 'message' => string]
public function maxCharsPerRequest(): int;
public function maxBatchSize(): int;           // 1 = no batching
```

`translateBatch()` is all-or-nothing per chunk: it returns a full ordered map or throws. Partial recovery is the caller's job.

**`TranslationProviderException`** replaces the current `str_contains($e->getMessage(), '429')` sniffing in **both** `TranslationService.php:~202` and `GenerateTranslations.php:~305`. It carries a `reason` and exposes `isRateLimited()`, `isRetryable()` (rate_limited, unavailable) and `isFatal()` (unauthorized, unsupported_language).

`isFatal()` is the important addition: a wrong API key today would be retried four times with 5/10/20/40s backoff *per string, for the whole catalogue*. Fatal reasons must abort the locale immediately.

**`AbstractTranslationProvider`** holds `providerCode()`, a default `translateBatch()` that loops `translate()` (so Google gets batch semantics free at `maxBatchSize() === 1`), and a chunker that splits by cumulative character count, not item count.

**`Providers/GoogleTranslateProvider`** — thin adapter over the existing scraper, translating its exceptions into the new type. `maxBatchSize() = 1`.

**`TranslationProviderManager`** — `driver()`, `default()` (from `Setting::get('translation_provider', 'google')`), `available()`, `forget()`. An unknown key **throws**; it never silently returns Google.

Bind in `AppServiceProvider::register()` with `scoped()`, not `singleton()`, so queue workers and Octane pick up settings changes between jobs.

**Consumers.** `TranslationService` drops its `GoogleTranslate` import and `getTranslator()` (~line 134) in favour of constructor injection — every current resolution is `app(TranslationService::class)` or job DI, so it's a drop-in. `tryTranslateText()` keeps its exact signature and null-on-failure contract. Add a sibling `tryTranslateBatch()`.

`GenerateTranslations` is a **second, independent provider integration** — it instantiates its own `new GoogleTranslate()` (~line 80) and type-hints `GoogleTranslate` in three signatures (`mergeTranslations` ~167, `translateArray` ~216, `translateString` ~254). All three change to `TranslationProvider`. Refactoring only `TranslationService` would leave the UI-string path on the scraper.

While in there, restructure `translateString()` into flatten → batch → reinflate: extract `{placeholder}` tokens and split pipe-plural forms first, then chunk. This turns hundreds of HTTP calls per locale into tens. Keep the `maxBatchSize() === 1` path byte-identical to today.

---

## Stage 2 — LibreTranslate provider

Uses the `Http` facade (not raw Guzzle) so `Http::fake()` works in tests.

```
POST {endpoint}/translate
{"q": <string|array>, "source": "en", "target": "pt-BR", "format": "text", "api_key": "..."}
```

Verified against LibreTranslate's source: `q` accepts an array (`batch = isinstance(q, list)`), and `translatedText` comes back as an array in the same order. `api_key` is read from the JSON body or query values. `GET /languages` returns `[{"code","name","targets"}]`.

**Guard the array length.** If `translatedText` has a different length than what was sent, throw. Silently zipping mismatched arrays would scramble titles across videos — the worst possible failure mode here.

`testConnection()` calls `/languages` and **diffs the result against the enabled locales mapped through `providerCode()`**, reporting e.g. *"Connected. 2 enabled languages have no model installed: Thai (th), Filipino (fil)."* With `LT_LOAD_ONLY` in play this is far more useful than a bare ping.

**Locale map**, most-specific wins: `config('translation.locale_map.libretranslate')` → `Setting::get('translation_locale_overrides')` → identity. Ships with `['pt' => 'pt-BR', 'no' => 'nb', 'fil' => 'tl']`.

---

## Stage 3 — Provenance columns

Migration adding two nullable columns to `translations`: `provider` (string 32, indexed with `locale`) and `source_locale` (string 12).

Without `provider`, the first question after switching engines — *"can I re-translate the Google-era rows?"* — has only one answer: truncate everything. Under scheduled-only that reverts the site to English for up to a month.

`source_locale` matters because `default_language` is admin-changeable; today a row translated from `en` is indistinguishable from one translated from `es`.

Both nullable and additive — the unique index on `(translatable_type, translatable_id, field, locale)` is untouched, and existing rows read as `NULL` = legacy.

For tags (cache-only, no room for a column), bump the key to `translation:text:{provider}:{locale}:{sha1}` so a provider switch invalidates naturally.

---

## Stages 4 + 5 — Remove on-view dispatch, add the scheduled run

**Ship these together.** Stage 4 alone leaves nothing translating new content.

### Dispatch sites to delete

`TranslationController.php:72,122` · `HomeController.php:322,423,460` · `DashboardController.php:79` · `PageController.php:56` · `VideoController.php:320`

Each already falls back to the source string on a cache miss — the dispatch was purely a side effect, so **no Vue/Inertia change is needed**. Rename `DashboardController::translatedOrQueued()` to `translatedFromCache()`.

`app/Jobs/TranslateTextJob.php` becomes fully dead (its only dispatchers are the two tag sites) — delete it. Tags move into the batch command, which is strictly better because it can use the array API.

**`PreTranslateVideoListener` stays.** It fires on `VideoProcessed` — queue-side, from the encoding pipeline, not a visitor render — so it doesn't violate the scheduled-only rule, and it's what stops a new upload sitting in English for a month. Gate it behind a `translation_pretranslate_on_upload` toggle, and have it dispatch **one job per video that loops locales internally** rather than one job per locale: with 30 locales a 200-video bulk import would otherwise burst 12,000 provider calls.

### `translations:run`

```
translations:run
  {--locale=*}  {--section=*}  {--limit=}  {--chunk=}  {--provider=}
  {--retranslate-other-providers}  {--dry-run}
```

Sections live in `app/Services/Translation/Sections/` behind a small interface (`pending()` / `store()`), keeping the command short and each section unit-testable:

- **videos** — public + approved, `whereNotExists` against `translations`; `title` also regenerates `translated_slug` via the existing `generateUniqueTranslatedSlug()`
- **categories** — `name`, `description`
- **pages** — `title`, `content`; `content` sends `format: 'html'`, split on block boundaries when over `maxCharsPerRequest()`. Google's scraper mangles HTML — keep plain-text behaviour for that driver.
- **tags** — **bounded** (top N by video count). On-view dispatch only ever translated tags people looked at; an unbounded sweep would attempt every tag × every locale on the first run.
- **ui** — delegates to `translations:generate --locale=X` in merge mode, reusing the placeholder/plural logic rather than duplicating it.

Idempotency comes purely from the `whereNotExists` / `Cache::has` checks — no cursor, no state file. A second run immediately after a first makes zero provider calls; assert that in a test. Failures are never persisted, so they retry next run.

On a retryable chunk failure, re-split into singles and retry each once, so one bad string doesn't poison 25 good ones. On `isFatal()`, abort the locale (and for `unauthorized`, the whole run) with a non-zero exit.

Run inline with `withoutOverlapping(120)`, `onOneServer()`, and a `--limit` default of 500 per section per locale so a run is bounded and the next tick continues.

Writes `translation_last_run_at` and `translation_last_run_summary` (per-locale/per-section counts, duration, provider, exit reason) for the admin panel.

### Scheduling

`->cron($expr)` takes a **string at registration time**, so a DB read there executes on every console bootstrap. Use a fixed registration with a lazy gate instead — the same idiom already proven by the `backup_enabled` skip closure:

```php
Schedule::command('translations:run')
    ->everyMinute()
    ->withoutOverlapping(120)
    ->onOneServer()
    ->runInBackground()
    ->skip(fn () => ! TranslationSchedule::isDueNow());
```

`app/Services/Translation/TranslationSchedule.php` exposes `isDueAt(CarbonInterface)` (pure, testable with `Carbon::setTestNow()`), `isDueNow()`, `describe()` and `nextRunAt()`. Zero DB access at registration; a frequency change takes effect the next minute with no deploy or cache clear.

`isDueNow()` must include **catch-up**: if the exact minute was missed (server down, `schedule:run` lag, overlap lock held) and `translation_last_run_at` is older than one period, run anyway. Without this a Monthly run missed by one minute is lost for a month.

Pin the timezone explicitly on both the schedule and `isDueAt()`, and label the UI field with the resolved zone.

---

## Stage 6 — Admin UI (`app/Filament/Pages/LanguageSettings.php`)

Three new sections above "Enabled Languages":

**Translation Provider** — `Select` (live), `TextInput` endpoint and `->password()->revealable()` API key both `->visible()` only for LibreTranslate, and a **Test Connection** action mirroring `StorageSettings::testStorageConnection()` exactly: `$this->save()` first, then `forget()`, then `testConnection()`, then a success/danger `Notification`. The provider helper text must say plainly that there is no automatic fallback.

**Translation Schedule** — frequency select (live), time picker, day-of-week/month, run limit, a `Placeholder` showing `describe()` + `nextRunAt()` + the computed cron read-only + the last-run summary, and a **Run Now** action (`Artisan::queue('translations:run')`). Run Now is essential now that nothing else can trigger a run.

**Language Code Overrides** (collapsed) — `KeyValue` seeded with the driver defaults so `pt → pt-BR` is visible and editable rather than mysterious.

**Fix `save()`.** It currently calls `Setting::set()` for four keys with no `$group`/`$type`, so booleans round-trip as strings. Replace with the generic loop already used in `SeoSettings::save()`, special-casing the API key to `Setting::setEncrypted(..., 'translation')` and skipping it when blank. Otherwise `translation_schedule_day` reads back as `"3"` and the schedule gate misbehaves.

Settings keys (group `translation`): `translation_provider`, `libretranslate_endpoint`, `libretranslate_api_key` (encrypted), `translation_locale_overrides` (json), `translation_schedule_frequency`, `translation_schedule_time`, `translation_schedule_day`, `translation_run_limit`, `translation_pretranslate_on_upload`, `translation_last_run_at`, `translation_last_run_summary`.

⚠️ `Setting::getAll()` caches raw values for 24h, so `Setting::get()` on an encrypted key returns **ciphertext**. Read the API key only via `getDecrypted()`, and consider excluding `type = 'encrypted'` rows from `getAll()` so no future caller can trip on it.

⚠️ `clearTranslationCache()` (line ~307) does `Cache::flush()` + `truncate translations` and promises re-translation "on next request" — which is now *never*. Reword to "on the next scheduled run", offer Run Now in the confirmation, and scope the truncate to a locale/provider.

Update the copy that hardcodes "Google Translate" (lines ~96, ~181, ~266).

---

## Self-hosting LibreTranslate for English → Spanish + Brazilian Portuguese

`docker-compose.yml` on the VPS:

```yaml
services:
  libretranslate:
    image: libretranslate/libretranslate:latest
    container_name: libretranslate
    restart: unless-stopped
    ports:
      - "127.0.0.1:5000:5000"        # localhost only; never expose publicly
    environment:
      LT_LOAD_ONLY: "en,es,pb"        # pb = the Argos *package* code; the API calls it pt-BR
      LT_DISABLE_WEB_UI: "true"
      LT_API_KEYS: "true"
      LT_REQ_LIMIT: "2000"
      LT_CHAR_LIMIT: "5000"
      LT_THREADS: "4"
      LT_UPDATE_MODELS: "true"
    volumes:
      # Must be /home/libretranslate/.local — NOT a deeper path.
      # The image creates and chowns .local to uid 1032; Docker only inherits
      # that ownership when the mount point already exists in the image. Mount
      # anything deeper and Docker creates it root-owned, so the container
      # (which runs as 1032) cannot write and dies with PermissionError.
      - lt_models:/home/libretranslate/.local:rw
      - lt_db:/app/db
volumes:
  lt_models:
  lt_db:
```

If you hit `PermissionError: [Errno 13] ... argos-translate/packages`, the volume was
created root-owned. Fixing the path alone is not enough — the bad volume persists.
Remove it first:

```bash
docker compose down -v && docker compose up -d
```

(The follow-on `Error: '' is not a valid port number.` is a cascade of the same
failure: the helper that resolves LT_PORT imports the same module and dies too.)

`LT_LOAD_ONLY: "en,es,pb"` is the key line — it downloads only the three models instead of all ~50, cutting first-boot time and RAM substantially. Note `pb` here and `pt-BR` in the API; see the table above. First start still takes several minutes while models download; watch with `docker compose logs -f`.

Binding to `127.0.0.1` means only the Laravel app reaches it, so the API key is defence-in-depth rather than the only control. If you later run it on a separate host, put it behind TLS and keep `LT_API_KEYS` on.

Create a key:

```bash
docker compose exec libretranslate ltmanage keys add 999999
```

Verify before touching the app:

```bash
curl -s http://127.0.0.1:5000/languages | jq '.[].code'      # expect en, es, pt-BR

curl -s -X POST http://127.0.0.1:5000/translate \
  -H 'Content-Type: application/json' \
  -d '{"q":["Hello world","Second string"],"source":"en","target":"pt-BR","api_key":"YOUR_KEY"}'
# expect {"translatedText":["Olá mundo","Segunda string"]}  ← array in, array out
```

That array round-trip is what the batch path depends on; confirm it before wiring anything up.

Then in **Admin → Languages**: provider `LibreTranslate`, endpoint `http://127.0.0.1:5000`, paste the key, **Test Connection**, and check the locale overrides show `pt → pt-BR`.

**Paid libretranslate.com** is the same provider class — set the endpoint to `https://libretranslate.com` and paste the key from your account. No code difference.

Two caveats worth knowing up front. LibreTranslate's output quality is materially below Google's for many languages, and it depends entirely on which Argos models are installed — the `/languages` diff in Test Connection is how you find gaps. And Brazilian and European Portuguese are genuinely different models: loading only `pb` means a future European Portuguese locale has no model until you add `pt` to `LT_LOAD_ONLY`.

---

## Verification

```bash
composer test
npm run build
```

New coverage, all offline:

- `tests/Support/FakeTranslationProvider.php` — records calls, primeable to throw a given exception on the Nth call. This is what makes everything downstream testable.
- **`NoOnViewDispatchTest`** — the regression test for the incident. `Bus::fake()`, enable a non-default locale, hit `/es/`, `/es/category/x`, `/es/tag/y`, a video page, a legal page, `/api/translate`, `/api/translate/batch` and the dashboard; assert `Bus::assertNothingDispatched()`, that responses are 200 with source text, **and that the fake provider recorded zero calls** so a future inline call is caught too.
- `LibreTranslateProviderTest` — `Http::fake()`: array in → ordered map out; `api_key` in the body not a header; `pt` maps to `pt-BR` in the outgoing `target`; a length-mismatched response throws; 429 → `isRateLimited()`, 403 → `isFatal()`, connection error → `unavailable()`.
- `TranslationScheduleTest` — `Carbon::setTestNow()` across all five frequencies; exactly one due minute per period, DST boundaries, catch-up, and `disabled` never due.
- `RunScheduledTranslationsTest` — rows written with `provider`/`source_locale`/`translated_slug`; chunk sizes honour `maxBatchSize`; **a second run makes zero provider calls**; a mid-chunk failure re-splits and persists survivors; a fatal exception aborts without writing.
- `GenerateTranslationsTest` — placeholders and plural pipes survive; failed keys are omitted, never written as English.
- `LanguageSettingsTest` — types round-trip (`translation_schedule_day` reads back as `int`); a blank API key preserves the stored one and plaintext never enters form state.

End-to-end on the VPS: point at LibreTranslate, Test Connection, `php artisan translations:run --dry-run`, then `--limit=5 --locale=es`, then confirm a Spanish video page renders translated text and `/es/<translated-slug>` resolves.

---

## Risks

1. **Scheduled-only + Monthly is a trap.** A new video sits in English for up to 30 days and has no `translated_slug`, so `getAlternateUrls()` emits no hreflang alternate for it. The UI should warn on Weekly/Monthly, and `PreTranslateVideoListener` should stay on.
2. **No fallback means a wrong API key = total silent failure** — the public site just renders English everywhere. Mitigated by `isFatal()` aborting fast, the last-run summary on the admin page, and a new `TranslationProviderCheck` health check for "last run failed" / "provider unreachable". Note `TranslationQueueCheck` stops being meaningful once on-view dispatch is gone.
3. **The `ui` section can't finish without `npm run build`.** Cron must not shell out to npm. Recommend the run sets a "rebuild needed" flag surfaced as a banner + one-click rebuild button on `LanguageSettings`, reusing the existing `processRegeneration()` build step.
4. **Tag cardinality** — must be bounded, see Stage 5.
5. **`Setting::getAll()` caching ciphertext** — see Stage 6.
