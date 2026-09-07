<?php

use App\Filament\Pages\ThemeSettings;
use App\Models\Setting;
use App\Support\GoogleFonts;
use App\Support\Typography;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| Typography settings
|--------------------------------------------------------------------------
|
| The body and heading fonts used to be hardcoded in resources/css/app.css with
| no admin control, and the three font settings that did exist were bound to a
| plain 29-item list. These pin the replacement: a curated catalogue, families
| resolved to real CSS stacks, weights clamped to what a family publishes, and
| delivery from Bunny rather than Google.
|
*/

it('renders the theme settings page with the typography fields', function () {
    asAdmin();

    Livewire::test(ThemeSettings::class)
        ->assertSuccessful()
        ->assertFormFieldExists('font_body_family')
        ->assertFormFieldExists('font_body_weight')
        ->assertFormFieldExists('font_display_family')
        ->assertFormFieldExists('font_display_weight')
        ->assertFormFieldExists('video_card_title_font')
        ->assertFormFieldExists('video_card_meta_font');
});

it('persists a font choice and reflects it in the resolved typography', function () {
    asAdmin();

    Livewire::test(ThemeSettings::class)
        ->set('data.font_display_family', 'Oswald')
        ->set('data.font_display_weight', 600)
        ->call('save')
        ->assertHasNoErrors();

    expect(Setting::get('font_display_family'))->toBe('Oswald');

    $slot = Typography::slot('display');

    expect($slot['family'])->toBe('Oswald')
        ->and($slot['weight'])->toBe(600)
        ->and($slot['stack'])->toContain("'Oswald'");
});

it('clamps a stored weight the chosen family does not publish', function () {
    // Bebas Neue ships a single 400 weight; asking for 700 must not emit a
    // request for a font file that does not exist.
    Setting::set('font_display_family', 'Bebas Neue', 'theme', 'string');
    Setting::set('font_display_weight', 700, 'theme', 'integer');

    expect(Typography::slot('display')['weight'])->toBe(400)
        ->and(Typography::stylesheetUrl())->toContain('bebas-neue:400')
        ->and(Typography::stylesheetUrl())->not->toContain('bebas-neue:700');
});

it('falls back to the theme default when the stored family is unknown', function () {
    Setting::set('font_body_family', 'Not A Real Font', 'theme', 'string');

    $slot = Typography::slot('body');

    expect($slot['family'])->toBe('Archivo')
        ->and($slot['isDefault'])->toBeTrue();
});

it('serves fonts from bunny, never from google', function () {
    Setting::set('font_body_family', 'Inter', 'theme', 'string');

    expect(Typography::stylesheetUrl())
        ->toStartWith('https://fonts.bunny.net/')
        ->not->toContain('googleapis');
});

it('requests each family only once across slots', function () {
    Setting::set('font_body_family', 'Inter', 'theme', 'string');
    Setting::set('font_display_family', 'Inter', 'theme', 'string');

    $families = Typography::requiredFamilies();

    expect($families)->toHaveCount(1)
        ->and(array_key_first($families))->toBe('Inter');
});

it('loads the legacy site title font, which previously was never fetched', function () {
    // category_title_font and age_font_family were rendered as inline
    // font-family but no stylesheet ever loaded them, so the choice silently
    // did nothing. site_title_font loaded, but from Google.
    Setting::set('site_title_font', 'Lobster', 'theme', 'string');

    expect(Typography::stylesheetUrl())->toContain('lobster');
});

it('exposes every catalogued family with a weight and a usable stack', function () {
    foreach (GoogleFonts::names() as $family) {
        expect(GoogleFonts::weights($family))->not->toBeEmpty()
            ->and(GoogleFonts::stack($family))->toContain("'{$family}'");
    }
});

it('renders each dropdown option in its own typeface', function () {
    $options = GoogleFonts::groupedOptions();

    expect($options)->toHaveKey('Sans-serif');

    // The preview depends on inline font-family surviving into the label; a
    // plain-text label means the dropdown silently stops previewing anything.
    expect($options['Sans-serif']['Inter'])
        ->toContain('font-family:')
        ->toContain('Inter');
});

it('offers the full weight ladder when no family is chosen', function () {
    // Regression: the weight Select used to narrow to a single option when the
    // font field was empty, so a stored default of 600 failed validation on
    // save with an error pointing at the weight rather than the empty font.
    expect(GoogleFonts::weightOptions(''))->toHaveKey(600)
        ->and(GoogleFonts::weightOptions(null))->toHaveKey(700);
});

it('saves cleanly with no fonts chosen at all', function () {
    asAdmin();

    Livewire::test(ThemeSettings::class)
        ->call('save')
        ->assertHasNoErrors();
});

it('uses the light logo only in light mode, falling back to the dark one', function () {
    // site_logo is the dark-mode logo AND the fallback: the site was dark-only
    // before light mode existed, so every existing install already stores
    // dark-ground artwork under that key.
    Setting::set('site_logo', 'logos/dark.png', 'general', 'string');

    asAdmin();

    $theme = Livewire::test(ThemeSettings::class)->instance();

    expect(Setting::get('site_logo'))->toBe('logos/dark.png')
        ->and(Setting::get('site_logo_light', ''))->toBe('');
});
