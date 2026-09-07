<?php

namespace App\Http\Controllers;

use App\Support\ThemeTokens;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    /**
     * Remember a signed-in user's light/dark choice so it follows them to other
     * devices. localStorage in useTheme.js is the primary store and has already
     * applied the change by the time this runs — this is a best-effort mirror,
     * which is why it returns quietly rather than surfacing failures.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(['light', 'dark'])],
        ]);

        // If the admin has pinned the site to one theme there is no per-user
        // choice to record, and accepting one would leave a stale preference
        // that reappears if they later re-enable the switcher.
        if (ThemeTokens::mode() !== 'user') {
            return response()->json(['saved' => false]);
        }

        $user = $request->user();

        if ($user) {
            $settings = $user->settings ?? [];
            $settings['theme'] = $validated['theme'];
            $user->forceFill(['settings' => $settings])->save();
        }

        return response()->json(['saved' => (bool) $user]);
    }
}
