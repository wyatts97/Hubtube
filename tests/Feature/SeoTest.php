<?php

use App\Models\Category;
use App\Models\Translation;
use App\Models\User;
use App\Models\Video;

/*
|--------------------------------------------------------------------------
| SEO — Sitemap, Robots.txt, Meta Tags
|--------------------------------------------------------------------------
*/

test('sitemap.xml returns valid XML', function () {
    $response = $this->get('/sitemap.xml');
    $response->assertStatus(200);
    // Laravel appends the charset, so match the media type rather than the
    // full header value.
    expect($response->headers->get('Content-Type'))->toStartWith('application/xml');
});

test('every static URL in the sitemap actually resolves', function () {
    // A sitemap entry pointing at a 404 burns crawl budget and shows up as an
    // error in Search Console — /live sat here after live streaming was removed.
    $xml = simplexml_load_string($this->get('/sitemap-pages.xml')->getContent());

    // Note: collect($xml->url) wraps the whole node list as a single item —
    // iterate the SimpleXMLElement directly.
    $paths = [];
    foreach ($xml->url as $url) {
        $paths[] = parse_url((string) $url->loc, PHP_URL_PATH) ?: '/';
    }
    $paths = array_unique($paths);

    expect($paths)->not->toBeEmpty();

    foreach ($paths as $path) {
        expect($this->get($path)->status())
            ->not->toBe(404, "Sitemap advertises {$path}, which 404s");
    }
});

test('robots.txt returns plain text', function () {
    $response = $this->get('/robots.txt');
    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');
    expect($response->getContent())->toContain('User-agent');
});

test('homepage returns Inertia response with seo prop', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('video page returns Inertia response with seo prop', function () {
    $video = Video::factory()->create();
    $response = $this->get("/{$video->slug}");
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('category page returns Inertia response with seo prop', function () {
    $category = Category::factory()->create();
    $response = $this->get("/category/{$category->slug}");
    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->has('seo'));
});

test('channel page returns Inertia response with seo prop', function () {
    $user = User::factory()->create();

    $response = $this->get("/channel/{$user->username}");

    $response->assertStatus(200);
    $response->assertInertia(
        fn ($page) => $page->component('Channel/Show')
            ->has('seo')
            ->where('seo.title', fn ($title) => str_contains($title, $user->username))
    );
});

/*
|--------------------------------------------------------------------------
| Server-rendered head — the tags crawlers actually see
|--------------------------------------------------------------------------
|
| Twitterbot and Facebookbot never execute JavaScript, and there is no Inertia
| SSR, so everything asserted here must be present in the raw HTML response.
*/

test('video page server-renders the real title, not the bare app name', function () {
    $video = Video::factory()->create(['title' => 'A Very Distinctive Video Title']);

    $response = $this->get("/{$video->slug}");

    $response->assertStatus(200);
    expect($response->getContent())->toMatch('/<title inertia>.*A Very Distinctive Video Title.*<\/title>/');
});

test('video page server-renders description, canonical and og tags', function () {
    $video = Video::factory()->create();
    $html = $this->get("/{$video->slug}")->getContent();

    expect($html)
        ->toContain('<meta name="description"')
        ->toContain('<link rel="canonical"')
        ->toContain('property="og:title"')
        ->toContain('name="twitter:card"');
});

test('server-rendered head emits exactly one JSON-LD block', function () {
    $video = Video::factory()->create();
    $html = $this->get("/{$video->slug}")->getContent();

    expect(substr_count($html, 'application/ld+json'))->toBe(1);
});

test('every keyed head tag is one SeoHead.vue also emits', function () {
    // Inertia's head manager REMOVES any inertia-attributed element the client
    // doesn't re-emit, so an unmatched key silently deletes a tag on hydration.
    $video = Video::factory()->create();
    $html = $this->get("/{$video->slug}")->getContent();

    preg_match_all('/ inertia="([^"]+)"/', $html, $matches);

    $allowedPrefixes = ['description', 'keywords', 'robots', 'canonical', 'schema', 'og:', 'twitter:'];

    foreach (array_unique($matches[1]) as $key) {
        $matched = false;
        foreach ($allowedPrefixes as $prefix) {
            if ($key === $prefix || str_starts_with($key, $prefix)) {
                $matched = true;
                break;
            }
        }
        expect($matched)->toBeTrue("Head key '{$key}' has no counterpart in SeoHead.vue");
    }
});

test('gated pages are noindex', function (string $path) {
    // HandleInertiaRequests::isIndexableRoute() derives this from route
    // middleware, so auth-gated pages are covered by the same branch. They
    // can't be asserted here because every layout-rendering route 500s under
    // sqlite (the sponsored_cards query uses MySQL-only JSON_LENGTH).
    $response = $this->get($path);

    $response->assertStatus(200);
    expect($response->getContent())->toContain('content="noindex, nofollow"');
})->with(['/login', '/register', '/forgot-password']);

test('long descriptions are truncated at a word boundary', function () {
    $words = trim(str_repeat('alpha bravo charlie delta echo foxtrot ', 20));
    $video = Video::factory()->create(['description' => $words]);

    preg_match('/<meta name="description" content="([^"]+)"/', $this->get("/{$video->slug}")->getContent(), $m);
    $description = $m[1];

    expect($description)->toEndWith('...');

    // Whatever survived must be a whole-word prefix of the original, so the
    // next character in the source is a space rather than mid-word.
    $kept = substr($description, 0, -3);
    expect($words)->toStartWith($kept)
        ->and(substr($words, strlen($kept), 1))->toBe(' ');
});

test('user page noindex honours its setting, but error pages never do', function () {
    App\Models\Setting::set('seo_noindex_user_pages', false, 'seo', 'boolean');
    App\Models\Setting::clearCache();

    expect($this->get('/login')->getContent())->not->toContain('content="noindex, nofollow"');

    // Error responses pass alwaysNoindex, so the toggle can't expose them.
    expect($this->get('/no-such-page-'.uniqid())->getContent())->toContain('content="noindex, nofollow"');
});

test('404 responses are noindex', function () {
    $response = $this->get('/this-route-does-not-exist-'.uniqid());

    $response->assertStatus(404);
    expect($response->getContent())->toContain('content="noindex, nofollow"');
});

/*
|--------------------------------------------------------------------------
| hreflang — built by SeoService, rendered server-side
|--------------------------------------------------------------------------
*/

test('no hreflang links when only one locale is enabled', function () {
    enableLocales(['en']);

    expect(hreflangLinks($this->get('/videos')))->toBeEmpty();
});

test('pages sharing a path advertise every enabled locale', function () {
    enableLocales(['en', 'es', 'pt']);

    expect(hreflangLinks($this->get('/videos')))->toBe([
        'x-default' => '/videos',
        'en' => '/videos',
        'es' => '/es/videos',
        // Region-aware BCP 47 code, per TranslationService::toHreflang().
        'pt-BR' => '/pt/videos',
    ]);
});

test('a locale-prefixed page advertises the same cluster as its default-locale twin', function () {
    enableLocales(['en', 'es']);
    $category = Category::factory()->create(['slug' => 'cars']);

    expect(hreflangLinks($this->get('/es/category/cars')))
        ->toBe(hreflangLinks($this->get('/category/cars')));
});

test('short first path segments are not mistaken for a locale prefix', function (string $path) {
    // A blind [a-z]{2,3} match treats "tag" and "pro" as locale prefixes and
    // strips them, pointing these pages' hreflang at the wrong URL.
    enableLocales(['en', 'es']);

    expect(hreflangLinks($this->get($path)))->toBe([
        'x-default' => $path,
        'en' => $path,
        'es' => '/es'.$path,
    ]);
})->with(['/tag/foo', '/pro']);

test('a translated video advertises its per-locale slug', function () {
    enableLocales(['en', 'es', 'pt']);
    $video = Video::factory()->create(['slug' => 'my-video']);
    Translation::create([
        'translatable_type' => Video::class,
        'translatable_id' => $video->id,
        'field' => 'title',
        'locale' => 'es',
        'value' => 'Mi Video',
        'translated_slug' => 'mi-video',
    ]);

    // pt is absent: only locales with a confirmed translated slug are claimed.
    expect(hreflangLinks($this->get('/my-video')))->toBe([
        'x-default' => '/my-video',
        'en' => '/my-video',
        'es' => '/es/mi-video',
    ]);
});

test('the per-locale slug a video advertises actually resolves', function () {
    enableLocales(['en', 'es']);
    $video = Video::factory()->create(['slug' => 'my-video']);
    Translation::create([
        'translatable_type' => Video::class,
        'translatable_id' => $video->id,
        'field' => 'title',
        'locale' => 'es',
        'value' => 'Mi Video',
        'translated_slug' => 'mi-video',
    ]);

    $this->get(hreflangLinks($this->get('/my-video'))['es'])->assertStatus(200);
});

test('canonical URLs never carry a trailing slash', function () {
    // public/.htaccess 301-redirects trailing slashes away, so a canonical with
    // one would point at a URL that immediately redirects.
    $video = Video::factory()->create();

    preg_match('/<link rel="canonical" href="([^"]+)"/', $this->get("/{$video->slug}")->getContent(), $m);

    expect($m[1])->not->toEndWith('/');
});

test('locale-prefixed homepage loads', function () {
    $response = $this->get('/es/');
    $response->assertStatus(200);
});

test('locale-prefixed trending loads', function () {
    $response = $this->get('/fr/trending');
    $response->assertStatus(200);
});

/*
|--------------------------------------------------------------------------
| Breadcrumbs & paginated series
|--------------------------------------------------------------------------
*/

test('a category page emits BreadcrumbList structured data', function () {
    $category = Category::factory()->create(['name' => 'Cars', 'slug' => 'cars']);

    $html = $this->get('/category/cars')->getContent();

    $breadcrumbs = null;
    preg_match_all('/<script type="application\/ld\+json"[^>]*>(.*?)<\/script>/s', $html, $matches);

    foreach ($matches[1] as $json) {
        $decoded = json_decode($json, true);
        foreach ((isset($decoded['@type']) ? [$decoded] : $decoded) as $node) {
            if (($node['@type'] ?? null) === 'BreadcrumbList') {
                $breadcrumbs = $node;
            }
        }
    }

    expect($breadcrumbs)->not->toBeNull()
        ->and($breadcrumbs['itemListElement'])->toHaveCount(3)
        ->and(array_column($breadcrumbs['itemListElement'], 'name'))->toBe(['Home', 'Categories', 'Cars'])
        ->and(array_column($breadcrumbs['itemListElement'], 'position'))->toBe([1, 2, 3]);
});

test('a video page emits BreadcrumbList structured data', function () {
    $category = Category::factory()->create(['name' => 'Cars', 'slug' => 'cars']);
    $video = Video::factory()->create(['category_id' => $category->id]);

    expect($this->get("/{$video->slug}")->getContent())->toContain('BreadcrumbList');
});

test('a paginated listing advertises the next page', function () {
    $category = Category::factory()->create(['slug' => 'cars']);
    Video::factory()->count(30)->create([
        'category_id' => $category->id,
        'privacy' => 'public',
        'is_approved' => true,
        'status' => 'processed',
    ]);

    $html = $this->get('/category/cars')->getContent();

    expect($html)->toContain('<link rel="next"')
        ->and($html)->not->toContain('<link rel="prev"');
});

test('an unpaginated listing advertises no series links', function () {
    Category::factory()->create(['slug' => 'empty']);

    $html = $this->get('/category/empty')->getContent();

    expect($html)->not->toContain('<link rel="next"')
        ->and($html)->not->toContain('<link rel="prev"');
});
