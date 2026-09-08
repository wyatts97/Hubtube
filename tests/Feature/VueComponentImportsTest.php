<?php

/**
 * Guards against a component being used in a Vue template but never imported.
 *
 * Vue does not fail on an unresolved component: it logs a console warning and
 * renders the tag as an unknown element, which drops the component's own
 * markup and leaves the children in normal document flow with no props, no
 * v-if gating and no event handlers bound. AdInterstitial shipped that way —
 * <BaseDialog> was never imported, so the interstitial's close button and ad
 * box rendered on every page instead of inside a gated modal, and the button
 * did nothing when clicked. There is no ESLint step in this project, so this
 * test is the only thing standing between that mistake and production.
 */

use Symfony\Component\Finder\Finder;

/** Vue built-ins never need importing. */
const VUE_BUILTIN_COMPONENTS = [
    'Transition', 'TransitionGroup', 'Teleport', 'Suspense', 'KeepAlive', 'Component',
];

/**
 * Names the <script> block binds: imports (default, named, aliased) and any
 * local const/let/var, which covers `const parts = computed(...)` style
 * dynamic components.
 */
function vueScriptBindings(string $script): array
{
    $bindings = [];

    preg_match_all('/^\s*import\s+([A-Za-z0-9_$]+)/m', $script, $m);
    $bindings = array_merge($bindings, $m[1]);

    preg_match_all('/^\s*import\s*\{([^}]*)\}/m', $script, $m);
    foreach ($m[1] as $group) {
        foreach (explode(',', $group) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            // `X as Y` binds Y.
            $parts = preg_split('/\s+as\s+/', $part);
            $bindings[] = trim(end($parts));
        }
    }

    preg_match_all('/(?:const|let|var)\s+([A-Za-z0-9_$]+)/', $script, $m);

    return array_merge($bindings, $m[1]);
}

test('every component used in a Vue template is imported', function () {
    $root = base_path('resources/js');

    $files = Finder::create()->files()->in($root)->name('*.vue');

    $unresolved = [];

    foreach ($files as $file) {
        $source = $file->getContents();

        $templateAt = strpos($source, '<template>');
        if ($templateAt === false) {
            continue;
        }

        $script = substr($source, 0, $templateAt);
        $template = substr($source, $templateAt);

        // HTML comments routinely name components in prose; stripping them
        // keeps a doc comment from being read as usage.
        $template = preg_replace('/<!--.*?-->/s', '', $template);

        $bindings = vueScriptBindings($script);

        preg_match_all('/<([A-Z][A-Za-z0-9]*)/', $template, $m);

        foreach (array_unique($m[1]) as $component) {
            if (in_array($component, VUE_BUILTIN_COMPONENTS, true)) {
                continue;
            }
            if (in_array($component, $bindings, true)) {
                continue;
            }

            $relative = str_replace(chr(92), '/', $file->getRelativePathname());
            $unresolved[] = "resources/js/{$relative} uses <{$component}>";
        }
    }

    expect($unresolved)->toBe([]);
});
