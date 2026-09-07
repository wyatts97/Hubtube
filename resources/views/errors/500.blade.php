<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Server Error</title>
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
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--color-bg-primary);
            color: var(--color-text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            text-align: center;
            padding: 2rem;
            max-width: 480px;
        }
        .code {
            font-size: 7rem;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(135deg, var(--color-accent), var(--color-accent-hover));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .title {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            color: #e5e5e5;
        }
        .desc {
            color: var(--color-text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: var(--color-accent);
            color: var(--color-text-primary);
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: background 0.2s;
        }
        .btn:hover { background: var(--color-accent-hover); }
    </style>
</head>
<body>
    <div class="container">
        <div class="code">500</div>
        <h1 class="title">Server Error</h1>
        <p class="desc">Something went wrong on our end. Please try again later or contact support if the problem persists.</p>
        <a href="/" class="btn">Back to Home</a>
    </div>
</body>
</html>
