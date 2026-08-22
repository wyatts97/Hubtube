# SEO & Language/Translation Refactor — HubTube

## Context

HubTube is a **Laravel 12 + Inertia 2 + Vue 3 SPA (no SSR)**, not a Nuxt app — so both SEO and i18n are hand-rolled in PHP and shipped to Vue as Inertia props. Both systems are ambitious and largely well-designed on paper (`SeoService` covers 18 page types with JSON-LD; `TranslationService` does machine translation, translated slugs and BCP-47 hreflang), but an audit found that several of the highest-value pieces **are computed and then never rendered**, and the two layers duplicate each other in ways that actively work against crawlers.

Three findings define the problem:

1. **Every page in the app serves `<title>HubTube</title>` to crawlers.** `app.blade.php:24` computes `$metaTitle` and line 8 never uses it. Titles only appear after Vue hydrates — invisible to Googlebot's initial pass, and entirely invisible to Twitterbot/Facebookbot which never run JS. This one line is the highest-impact defect in the codebase.
2. **After hydration the DOM has two of everything.** Blade emits description/canonical/og/twitter/JSON-LD, then `SeoHead.vue` appends its own copies, because none of the Blade tags carry the `inertia` attribute that lets Inertia's head manager *replace* rather than *append*.
3. **The UI translation layer silently fails open.** `useI18n.js:59` returns the raw dot-key on a miss, which makes all 180+ `t('x') || 'Fallback'` guards dead code, and there is no fallback to English — so the 35 locales advertised in the admin panel but missing a JSON file render a UI made entirely of dot-paths.

Decisions taken: **no Inertia SSR** (harden the Blade path instead), **queue translation but keep the free scraper**, **repair the custom `t()` rather than adopt vue-i18n**, and **defects only** — no new features. New-feature ideas are recorded in an appendix but explicitly out of scope.

---

## Phase 1 — Make crawlers see the right head

### 1.1 Server-render the title
`resources/views/app.blade.php:8` — the `@php` block that computes `$metaTitle` already runs before this line is emitted; move it above the `<title>` and use it.

```blade
<title inertia>{{ $metaTitle ?? config('app.name', 'HubTube') }}</title>
```

The `inertia` attribute stays so client-side navigation still updates it.

### 1.2 Stop the post-hydration duplication
Add `inertia="<key>"` to every SEO tag in `app.blade.php` (description, keywords, robots, canonical, each `og:*`, each `twitter:*`, the JSON-LD `<script>`), and the matching `head-key` to the same tags in `resources/js/Components/SeoHead.vue`. Inertia's head manager keys off that attribute and replaces the server tag in place instead of appending a second one.

Keys must match exactly on both sides — e.g. Blade `<meta name="description" inertia="description">` pairs with `<meta head-key="description" name="description" …>` in `SeoHead.vue`.

For the repeated tags (`og:locale:alternate`, `og:video:tag`) use an indexed key (`og:video:tag:0`, …) generated from the loop index on both sides.

### 1.3 Give every page a `SeoHead`
37 of 49 pages use a plain `<Head title="…">` and drop the `seo` prop, so client-side title/OG drift from what Blade rendered. `resources/js/Pages/Videos/Index.vue:124` is the starkest — hardcoded `"Browse Videos"` against a full `forVideosIndex()` payload.

- Public content pages that already receive a `seo` prop: swap `<Head title>` for `<SeoHead :seo="seo" />`. Representative: `Videos/Index.vue`, `Shorts/Index.vue`, `Categories/Index.vue`, `Tags/Index.vue`, `PublicPlaylists.vue`, `Legal/Show.vue`, `Contact.vue`, `Dmca.vue`.
- Authenticated/private pages (`Auth/*`, `Settings`, `Dashboard`, `Wallet/*`, `Pro/*`, `Rewards`, `History`, `Feed`, `Videos/{Create,Edit,Status,UploadSuccess}`, `Images/Upload`, `Galleries/Create`, `Channel/{WatchHistory,LikedVideos}`) do not need OG data but **must** emit `noindex, nofollow`. Add a `SeoService::forPrivatePage(string $title)` helper that returns a minimal `baseMeta()` with `robots => 'noindex, nofollow'`, and call it from those controllers.
- `resources/js/Pages/Error.vue:58` currently emits no robots directive on 404/500 responses. Route it through the same helper.

### 1.4 Render the two orphaned payload keys
`baseMeta()` (`SeoService.php:876-877`) populates `thumbnailAlt` and `alternateUrls` and nothing consumes either.
- `alternateUrls` — this is the same data the Blade hreflang block re-derives with fresh DB queries. Phase 2 resolves it.
- `thumbnailAlt` — computed in `forVideo()` from `seo_video_thumbnail_alt_template`. Consume it in `resources/js/Pages/Videos/Show.vue` on the poster/thumbnail `<img>`, and pass it into `VideoCard.vue`'s `alt` where a video object carries it.

---

## Phase 2 — Get hreflang out of the Blade template

`app.blade.php:207-264` is ~55 lines of routing logic and Eloquent queries inside a view. On every video page it runs `Video::where('slug',…)->first()` and a `whereHas('translations')` — **re-querying a model the controller already loaded and already handed to `SeoService::forVideo()`**.

**Move it into `SeoService`.** `forVideo()` already calls `TranslationService::getAlternateUrls()` at line 274 and stores the result in `$seo['alternateUrls']` — the work is done, it just isn't reaching the view.

1. Add `SeoService::hreflangTags(): array` returning the final `[$hreflangCode => $url]` map, including `x-default`. It reads `$this->currentSeo['alternateUrls']` when present (video pages) and falls back to `TranslationService::hreflangMapForPath()` otherwise. The BCP-47 conversion stays in `TranslationService::toHreflang()`.
2. `app.blade.php` collapses to:
   ```blade
   @foreach(\App\Services\SeoService::hreflangTags() as $hl => $href)
       <link rel="alternate" hreflang="{{ $hl }}" href="{{ $href }}" />
   @endforeach
   ```
   These links must **not** carry an `inertia` attribute — `SeoHead.vue` doesn't emit hreflang, and the head manager deletes any keyed tag the client doesn't re-render.
3. Strip the locale prefix by matching the first path segment against the enabled locales, not with a `[a-z]{2,3}` pattern — that also eats real first segments like `/tag/…` and `/pro`, pointing those pages' hreflang at the wrong URL.

Also resolve the trailing-slash contradiction: `public/.htaccess` 301-redirects trailing slashes *away* while `seo_force_trailing_slash` (`SeoService.php:92`) adds them, so enabling that setting produces canonicals that redirect. Remove the setting and the branch.

**Dropped from this phase:** extending `alternateUrls` to `forCategory()` and `forPage()`. Only videos have per-locale slugs — `VideoController::localeShow()` resolves them, but `HomeController::localeCategory()` and `PageController::localeShow()` look up the original slug only. Advertising translated-slug URLs for those pages would emit hreflang pointing at 404s. Making them resolve translated slugs is a prerequisite, and belongs with the route consolidation in the appendix.

---

## Phase 3 — Sitemap, robots and settings reconciliation

- **`/live` 404** — `app/Http/Controllers/SitemapController.php:103` emits `/live` into `sitemap-pages.xml`, but the route was deleted (migration `2025_02_24_000001_drop_live_streaming_tables.php`). Remove the entry, and delete the dead `SeoService::forLive()` and the `seo_live_*` settings alongside it. `/shorts` is a real route that was missing from the sitemap — add it in place of `/live`.
- **Orphaned settings** — `seo_noindex_user_pages`, `seo_locale`, and `seo_sitemap_max_videos` are exposed in `app/Filament/Pages/SeoSettings.php` (lines 211, 172, 396) and read by nothing. Either wire them up or remove the form fields; recommend wiring `seo_noindex_user_pages` into the Phase 1.3 `forPrivatePage()` helper, and deleting the other two (`SeoService` uses `App::getLocale()`; `SitemapController` reads `seo_sitemap_chunk_size`).
  - `forPrivatePage()` is shared with error responses, so it takes an `$alwaysNoindex` flag: the setting governs signed-in account pages, while 404/500/419 stay noindex regardless of how it's toggled.
  - The setting's default must be `true` on both sides — `SeoSettings::mount()` previously defaulted it to `false`, which would have shown the toggle off while the directive was being emitted.
- **Unexposed settings** — ~17 keys the code reads have no admin UI (`seo_sitemap_chunk_size`, `seo_sitemap_max_{tags,images,galleries,playlists}`, `seo_sitemap_{images,galleries,playlists}_enabled`, `seo_{videos,categories,tags,images,galleries}_index_{title,description}`, `seo_public_playlists_*`, `seo_shorts_*`). Add them to the existing Sitemap and Page Templates tabs so the defaults are visible and editable.
- **`truncate()` doesn't do what its docblock says** — `SeoService.php:70-79` claims "word boundary" but cuts mid-word. Cut at the last space before the limit.

---

## Phase 4 — Repair the UI translation layer

All in `resources/js/Composables/useI18n.js`.

### 4.1 Fallback chain + honest misses
`t()` currently returns the key on a miss. Change to: **current locale → English → the key**, and warn once per key in dev.

This requires English always being available client-side. Add `'fallback' => $translations` for `en.json` to `getLocaleData()` in `app/Http/Middleware/HandleInertiaRequests.php:229-236` (skip it when the current locale *is* the default, to avoid doubling the payload).

```js
const t = (key, params = {}) => {
    const hit = lookup(translations.value, key) ?? lookup(fallback.value, key);
    if (hit == null) {
        if (import.meta.env.DEV) warnOnce(key);
        return key;
    }
    return interpolate(hit, params);
};
```

This alone fixes the "enable French, get a UI of dot-paths" failure mode for all 35 locales without JSON files.

### 4.2 Plurals
`t('video.views', {count})` renders `"1 views"` in English and is wrong in every Slavic and Arabic locale. Add `Intl.PluralRules` support with a pipe convention, resolved inside `interpolate()`:

```json
"views": "{count} view | {count} views"
```

Migrate the existing hand-rolled ternaries at `Components/PlaylistsRail.vue:44`, `Pages/PublicPlaylists.vue:104` and `Composables/useFormatters.js:43` onto it.

### 4.3 Fix the reactivity bug
`useI18n.js:43-46` writes `document.documentElement.lang`/`dir` at **composable setup time**, inside every one of the 59 components that call it — redundant and non-reactive. Delete it; `app.blade.php:2` already sets both correctly server-side, and the locale switch is a full page reload anyway.

### 4.4 Cache the locale JSON
`HandleInertiaRequests.php:224-227` does `file_exists` + `file_get_contents` + `json_decode` of a ~16KB file **on every single request**, including partial reloads. Wrap in `Cache::rememberForever("i18n:{$locale}")`, invalidated by `php artisan translations:generate` (`app/Console/Commands/GenerateTranslations.php`) and by a new `translations:clear-cache`.

### 4.5 Backfill and clean the key catalogue
- **24 keys used in code but absent from `en.json`** currently render as raw dot-paths to users. The entire `dmca` namespace (11 keys) is missing while `Pages/Dmca.vue` uses it. Also: `auth.continue_with`, `auth.or`, `channel.no_liked_videos`, `common.{channels,error,remove,views}`, `footer.dmca_request`, `search.no_results`, `tags.{browse,no_tags}`, `video.shortcuts.title`, `video.tap_play_to_resume`, `wallet.no_transactions_desc`. Add all to `en.json`, then regenerate `es.json`.
- **`es.json` is 14 keys behind** — the whole `rewards` namespace is absent, so the Rewards page renders raw keys in Spanish. Run `php artisan translations:generate es --force` after the `en.json` backfill. **Still outstanding:** the command needs DB access (it applies `TranslationOverride` rows) and `pdo_mysql` isn't available locally, so `es.json` sits 47 keys behind. 4.1 makes that render English rather than dot-paths, so it is no longer user-breaking — but the command should be run once on a machine with a database.
- **Strip the dead `|| 'Fallback'` guards** now that 4.1 makes fallback real — 386 of them, plus 59 `tSafe(key, fallback)` calls and 6 `t('x') !== 'x' ? … : …` ternaries. `tSafe` was a *working* fallback helper duplicated verbatim in 8 files; it becomes redundant and its literals are the best source of English text for keys `en.json` never defined. Recover those literals into `en.json` **before** stripping.
  - Watch for literals containing an escaped quote (`copyright owner\'s`): a naive non-greedy regex stops at the backslash and leaves a fragment behind. Two lines hit this — `Pages/Dmca.vue` and `Pages/History.vue`.
- **Delete orphan keys**: the `live` namespace (13 keys, feature removed) and `embedded` (4 keys) are gone for good and were dropped. The other ~61 orphans are generic UI vocabulary (`common.close`, `errors.*`, `toast.*`); they were **kept** — the orphan set proved sensitive to scan quality (adding `tSafe` coverage moved 18 keys out of it), and ~2KB of payload doesn't justify deleting strings a future component may need.

### 4.6 Composable cleanup
Drop the unused `router` import in `useI18n.js` and the unused `router`/`useFetch`/`post` imports in `useTranslation.js`.

**Correction:** the plan claimed `resources/js/Composables/useAutoTranslate.js` is imported by no `.vue` file. It is — `Pages/{Home,Search,Trending,Videos/Index}.vue` all call `useAutoTranslate(['title'])` and use both `translateVideos` and `tr`. It was **not** deleted.

---

## Phase 5 — Move content translation off the request path

`TranslationService` enforces `minDelayMs = 1200` between calls with 5/10/20/40s retry backoff. `useGlobalAutoTranslate.js` fires a batch of up to 50 video IDs on every Inertia navigation, so **a cold batch blocks a PHP-FPM worker for 60+ seconds** and risks a gateway timeout. `AppLayout.vue:1159` papers over this with a blocking 3-second overlay.

Horizon is already installed and configured.

1. Add `app/Jobs/TranslateModelJob.php` — takes `(string $type, int $id, array $fields, string $locale)`, calls the existing `TranslationService::translateModel()`, writes to the `translations` table. Dedupe with `ShouldBeUnique` keyed on type+id+locale.
2. `TranslationController::translateBatch()` (`app/Http/Controllers/TranslationController.php`) returns **immediately** with whatever translations already exist in the DB, and dispatches `TranslateModelJob` for the misses. No synchronous translation in an HTTP request, ever.
3. `useGlobalAutoTranslate.js` renders whatever comes back and drops the blocking overlay — untranslated titles show source text and are translated by the next visit. Remove `MIN_OVERLAY_MS` (`AppLayout.vue:1159`).
4. Dispatch `TranslateModelJob` from the existing video-publish path (next to `app/Listeners/SubmitVideoToIndexNowListener.php`) so new videos are pre-translated into every enabled locale before anyone requests them — this is what makes step 3 acceptable in practice.
5. **`auto_translate_content` is dead** — written and read only inside `app/Filament/Pages/LanguageSettings.php:68,94,137`; content is auto-translated regardless. Make `TranslateModelJob` and the controller honour it.

---

## Verification

No frontend test suite exists (`COMPONENTOVERHAUL.MD` confirms this); the backend has Pest with an existing `tests/Feature/SeoTest.php` to extend.

**Automated**
```
composer test          # extend tests/Feature/SeoTest.php
composer lint          # Pint
```
New Pest coverage:
- `GET /` and `GET /{video:slug}` — assert the response HTML contains `<title>` with the real page title, **not** the bare app name. This is the regression test for the headline defect.
- Assert exactly one `application/ld+json` block per response.
- Assert private routes (`/settings`, `/dashboard`, `/wallet`) emit `noindex`.
- Assert `sitemap-pages.xml` contains no URL that 404s — iterate the sitemap and hit each route.
- Assert `hreflang` output for a video with translated slugs, and that a video page issues no duplicate `Video` query (`DB::listen` count).
- `TranslationController@translateBatch` returns in <200ms with an empty translation table and queues jobs (`Queue::fake()`).

**Manual**
```
npm run dev
```
- `view-source:` a video page — confirm real title, one JSON-LD block, canonical, hreflang. Then hydrate and re-inspect the live DOM to confirm no tag doubled.
- Paste a video URL into the Facebook Sharing Debugger and Twitter Card Validator — both must resolve without executing JS.
- Google Rich Results Test on a video page and a category page.
- Switch to Spanish, visit `/es/rewards` — no raw dot-keys should appear anywhere.
- Enable French in the admin panel *without* creating `fr.json` — the UI must render English, not dot-paths (validates 4.1).
- Load a channel page in a fresh locale — page must render immediately, no 3-second overlay, titles translated on second visit.

---

## Appendix — Recorded but out of scope

Deliberately excluded per the "defects only" decision; listed so they aren't lost.

**SEO:** `BreadcrumbList` JSON-LD (`UI/Breadcrumbs.vue` is visual-only and wired into just 2 pages) · `SeekToAction`/`Clip` on `VideoObject` · `rel=prev`/`rel=next` on paginated listings (absent everywhere) · RSS/Atom feeds · `fetchpriority="high"` on LCP images (used nowhere; above-fold cards are `loading="lazy"`) · an `seo:health` artisan command.

**i18n:** `Accept-Language` detection for first-time visitors (nothing reads it today) · a durable `users.locale` column (locale currently lives in the PHP session, lost on expiry, incompatible with full-page caching) · locale-aware date/number/currency formatting (`timeAgo()`'s `locale` arg is omitted at all 3 call sites; `Wallet/Deposit.vue:31` hardcodes USD) · real RTL support (`ar`/`he` are selectable in admin but there are zero `rtl:` variants or logical properties — the layout breaks).

**Consolidation:** collapse the ~35 duplicated `locale.*` routes and their parallel `localeShow`/`localeCategory`/`localeTag` controller methods into one group (`routes/web.php:504-541`) · unify the four hardcoded copies of the 37-language list (`TranslationService::LANGUAGES`, `TranslationService::toHreflang()`, `SeoService::toOgLocale()`, `LanguageSwitcher.vue`'s `localeToCountry`) into one registry · self-host the flag images currently pulled from `flagcdn.com` on every render · reconcile the dual tagging systems (`Video.tags` JSON column vs. the `Hashtag` + `video_hashtags` pivot).
