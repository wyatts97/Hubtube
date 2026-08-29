<?php

use App\Http\Middleware\CheckInstalled;
use App\Models\User;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Install Guard
|--------------------------------------------------------------------------
|
| The storage/installed marker used to be the only signal that the site was
| installed. Losing it — a restored backup, a wiped storage/ dir, a deploy that
| did not preserve storage/ — sent every request on a live site to /install and
| exposed an unauthenticated setup wizard that can rewrite .env and create an
| administrator. A populated database now counts as proof of install.
|
*/

beforeEach(function () {
    $this->marker = storage_path('installed');
    $this->unlock = storage_path('install-unlock');
    $this->markerBackup = File::exists($this->marker) ? File::get($this->marker) : null;

    File::delete($this->marker);
    File::delete($this->unlock);
    CheckInstalled::flushProbeCache();
});

afterEach(function () {
    File::delete($this->unlock);
    if ($this->markerBackup !== null) {
        File::put($this->marker, $this->markerBackup);
    }
    CheckInstalled::flushProbeCache();
});

test('a live site stays up when the installed marker is lost', function () {
    User::factory()->admin()->create();
    CheckInstalled::flushProbeCache();

    $this->get('/')->assertStatus(200);
});

test('the lost marker is self-healed so the probe runs once', function () {
    User::factory()->admin()->create();
    CheckInstalled::flushProbeCache();

    expect(File::exists($this->marker))->toBeFalse();

    $this->get('/');

    expect(File::exists($this->marker))->toBeTrue();
});

test('the installer stays blocked when the marker is lost but the site is installed', function () {
    User::factory()->admin()->create();
    CheckInstalled::flushProbeCache();

    $this->get('/install')->assertRedirect('/');
});

/**
 * Run the middleware in isolation.
 *
 * The install *pages* shell out to `which ffmpeg` for the requirements screen,
 * which fails on Windows CI, so the guard is asserted at the middleware level
 * rather than by rendering /install.
 */
function installGuard(string $mode, string $uri = '/install'): string
{
    $middleware = new CheckInstalled();

    $response = $middleware->handle(
        Illuminate\Http\Request::create($uri, 'GET'),
        fn () => new Illuminate\Http\Response('passed-through'),
        $mode
    );

    return $response->isRedirect() ? 'redirect:' . $response->headers->get('Location') : 'passed-through';
}

test('the installer is reachable when there is genuinely no admin', function () {
    CheckInstalled::flushProbeCache();

    expect(installGuard('block'))->toBe('passed-through');
});

test('a non-admin user alone does not count as installed', function () {
    User::factory()->create();
    CheckInstalled::flushProbeCache();

    expect(installGuard('block'))->toBe('passed-through');
});

test('the operator escape hatch reopens the installer', function () {
    User::factory()->admin()->create();
    File::put($this->unlock, '');
    CheckInstalled::flushProbeCache();

    expect(installGuard('block'))->toBe('passed-through');
});

test('the escape hatch wins even over an intact marker', function () {
    User::factory()->admin()->create();
    File::put($this->marker, now()->toDateTimeString());
    File::put($this->unlock, '');
    CheckInstalled::flushProbeCache();

    expect(installGuard('block'))->toBe('passed-through');
});

test('a genuinely uninstalled site is pushed to the installer', function () {
    CheckInstalled::flushProbeCache();

    // From a normal page. Requests already under /install pass through by design,
    // otherwise the wizard would redirect to itself forever.
    expect(installGuard('require', '/'))->toStartWith('redirect:');
    expect(installGuard('require', '/install'))->toBe('passed-through');
});
