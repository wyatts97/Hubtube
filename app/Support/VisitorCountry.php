<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The visitor's ISO country code, as reported by Cloudflare.
 *
 * Requests that never passed through Cloudflare (local dev, direct-to-origin)
 * carry no header and resolve to null. Cloudflare's own 'XX' (unknown) and
 * 'T1' (Tor) are returned as-is so callers can decide how to treat them.
 */
class VisitorCountry
{
    public static function fromRequest(Request $request): ?string
    {
        $code = strtoupper(trim((string) $request->header('CF-IPCountry', '')));

        return preg_match('/^[A-Z0-9]{2}$/', $code) ? $code : null;
    }
}
