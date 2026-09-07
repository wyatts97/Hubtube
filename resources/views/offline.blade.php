<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Offline - HubTube</title>
    <style>
        /* Palette from App\Support\ThemeTokens — the same source the SPA uses, so
           a standalone page can't drift from the site it belongs to. */
        :root { {!! \App\Support\ThemeTokens::cssVariables('dark') !!} }
        :root.theme-light { {!! \App\Support\ThemeTokens::cssVariables('light') !!} }
    </style>
    <script>
        (function () {
            try {
                if ({!! json_encode(\App\Support\ThemeTokens::authoritativeMode()) !!}) return;
                var saved = localStorage.getItem('ht-theme');
                document.documentElement.classList.toggle('theme-light', saved === 'light');
            } catch (e) {}
        })();
    </script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: var(--color-bg-primary); color: var(--color-text-primary); font-family: Inter, system-ui, sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .container { text-align: center; padding: 2rem; }
        .icon { font-size: 4rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: var(--color-text-secondary); margin-bottom: 1.5rem; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: var(--color-accent); color: var(--color-text-primary); border-radius: 0.5rem; text-decoration: none; font-weight: 500; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">📡</div>
        <h1>You're Offline</h1>
        <p>Check your internet connection and try again.</p>
        <a href="/" class="btn" onclick="window.location.reload(); return false;">Retry</a>
    </div>
</body>
</html>
