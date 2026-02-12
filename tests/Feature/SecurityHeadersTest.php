<?php

/*
|--------------------------------------------------------------------------
| Security Headers — CSP, X-Frame-Options, etc.
|--------------------------------------------------------------------------
| Verify all security headers are present on responses.
*/

test('response includes Content-Security-Policy header', function () {
    $response = $this->get('/');
    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');
    expect($csp)->toContain("default-src 'self'");
    expect($csp)->toContain("object-src 'none'");
    expect($csp)->toContain("frame-ancestors 'self'");
});

test('response includes X-Content-Type-Options header', function () {
    $response = $this->get('/');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('response includes X-Frame-Options header', function () {
    $response = $this->get('/');
    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
});

test('response includes X-XSS-Protection header', function () {
    $response = $this->get('/');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
});

test('response includes Referrer-Policy header', function () {
    $response = $this->get('/');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

test('response includes Permissions-Policy header', function () {
    $response = $this->get('/');
    $csp = $response->headers->get('Permissions-Policy');
    expect($csp)->toContain('camera=()');
    expect($csp)->toContain('geolocation=()');
});
