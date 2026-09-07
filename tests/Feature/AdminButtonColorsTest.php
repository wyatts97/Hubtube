<?php

use Filament\Actions\Action;

/*
|--------------------------------------------------------------------------
| Admin action colours
|--------------------------------------------------------------------------
|
| Filament renders any action without an explicit ->color() in the panel's
| `primary`, which here is a muted red (#ba4f49 at shade 600). That made every
| Save, Create and Test button read as destructive. Each action now declares a
| colour, and `primary` is reserved for identity — active nav, links, focus
| rings — rather than buttons.
|
*/

it('leaves no admin action falling back to the primary red', function () {
    $files = collect(
        (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Filament'))))
    )->filter(fn ($f) => $f->isFile() && $f->getExtension() === 'php');

    $offenders = [];

    foreach ($files as $file) {
        $src = file_get_contents($file->getPathname());

        // Split so each action's own chain is inspected, not its neighbour's.
        $chunks = preg_split('/(?=Action::make\()/', $src);
        array_shift($chunks);

        foreach ($chunks as $chunk) {
            if (! preg_match("/Action::make\(\s*'([^']+)'/", $chunk, $m)) {
                continue;
            }

            $end = strpos($chunk, "),\n");
            $chain = $end === false ? $chunk : substr($chunk, 0, $end);

            if (! str_contains($chain, '->color(')) {
                $offenders[] = basename($file->getPathname()) . ':' . $m[1];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('colours save and create actions as constructive, not destructive', function () {
    $src = file_get_contents(app_path('Filament/Pages/ThemeSettings.php'));

    expect($src)->toContain("Action::make('save')");

    $chunk = substr($src, strpos($src, "Action::make('save')"), 400);

    expect($chunk)->toContain("->color('success')")
        ->and($chunk)->not->toContain("->color('danger')");
});
